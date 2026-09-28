"""Builds a translated .po from a fresh .pot and existing translations.

Usage:
    python bin/po-merge.py <template.pot> <output.po> <source.po> [<source.po> ...]
                           [--extra <translations.json>] [--report <untranslated.json>]

Every entry of the template keeps its comments and references and gets the
msgstr a source file has for the same msgctxt + msgid. The header is taken
from the first source. `--extra` adds translations from a JSON object keyed by
msgid (a list for plural forms) and wins over the sources; `--report` writes
the msgids that are still untranslated.

WP-CLI can extract strings and compile .mo files, but it cannot carry German
over from four separate text domains into one, which is what merging the
addons into the core needed. Plain Python, no polib, because nothing else in
this repository needs it.
"""

import io
import json
import re
import sys


def unquote(line):
    body = line[line.index('"') + 1:line.rindex('"')]
    return bytes(body, 'utf-8').decode('unicode_escape').encode('latin-1').decode('utf-8')


def quote(text):
    text = text.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n').replace('\t', '\\t')
    return '"' + text + '"'


def parse(path):
    """Returns a list of entries: dicts with comments, ctxt, id, plural, strs."""
    entries = []
    entry = None
    field = None

    def flush():
        if entry is not None and (entry['id'] is not None):
            entries.append(entry)

    for raw in io.open(path, encoding='utf-8'):
        line = raw.rstrip('\n')

        if line.startswith('#'):
            if entry is not None and entry['id'] is not None and field != 'comments':
                flush()
                entry = None
            if entry is None:
                entry = {'comments': [], 'ctxt': None, 'id': None, 'plural': None, 'strs': {}}
            entry['comments'].append(line)
            field = 'comments'
            continue

        if line.strip() == '':
            flush()
            entry = None
            field = None
            continue

        if entry is None:
            entry = {'comments': [], 'ctxt': None, 'id': None, 'plural': None, 'strs': {}}

        m = re.match(r'^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+(".*")$', line)

        if m:
            key, index, value = m.group(1), m.group(2), unquote(m.group(3))
            if key == 'msgctxt':
                entry['ctxt'] = value
                field = ('ctxt',)
            elif key == 'msgid':
                entry['id'] = value
                field = ('id',)
            elif key == 'msgid_plural':
                entry['plural'] = value
                field = ('plural',)
            else:
                i = int(index) if index is not None else 0
                entry['strs'][i] = value
                field = ('strs', i)
            continue

        if line.startswith('"') and field:
            value = unquote(line)
            if field[0] == 'strs':
                entry['strs'][field[1]] += value
            else:
                entry[field[0]] += value

    flush()

    return entries


def key(entry):
    return (entry['ctxt'] or '', entry['id'])


def main(argv):
    extra = {}
    report = None
    args = []
    i = 0

    while i < len(argv):
        if argv[i] == '--extra':
            extra = json.load(io.open(argv[i + 1], encoding='utf-8'))
            i += 2
        elif argv[i] == '--report':
            report = argv[i + 1]
            i += 2
        else:
            args.append(argv[i])
            i += 1

    template, output, sources = args[0], args[1], args[2:]
    known = {}
    header = None

    for source in sources:
        for entry in parse(source):
            if entry['id'] == '':
                if header is None:
                    header = entry
                continue
            if any(entry['strs'].values()) and key(entry) not in known:
                known[key(entry)] = entry['strs']

    for msgid, value in extra.items():
        known[('', msgid)] = {i: v for i, v in enumerate(value)} if isinstance(value, list) else {0: value}

    out = []
    missing = []

    if header is not None:
        out.append('\n'.join(header['comments']))
        out.append('msgid ""')
        out.append('msgstr ""')
        for part in re.findall(r'[^\n]*\n', header['strs'][0]):
            out.append(quote(part))
        out.append('')

    for entry in parse(template):
        if entry['id'] == '':
            continue

        strs = known.get(key(entry))

        if not strs:
            missing.append(entry['id'])
            strs = {0: '', 1: ''} if entry['plural'] is not None else {0: ''}

        out.extend(entry['comments'])
        if entry['ctxt'] is not None:
            out.append('msgctxt ' + quote(entry['ctxt']))
        out.append('msgid ' + quote(entry['id']))
        if entry['plural'] is not None:
            out.append('msgid_plural ' + quote(entry['plural']))
            for index in sorted(strs):
                out.append('msgstr[%d] %s' % (index, quote(strs[index])))
        else:
            out.append('msgstr ' + quote(strs.get(0, '')))
        out.append('')

    io.open(output, 'w', encoding='utf-8', newline='\n').write('\n'.join(out))

    if report:
        json.dump(missing, io.open(report, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)

    print('%d untranslated' % len(missing))


if __name__ == '__main__':
    main(sys.argv[1:])
