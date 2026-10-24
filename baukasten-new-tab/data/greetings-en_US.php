<?php
/**
 * Greetings for en_US, and for every locale without a file of its own.
 *
 * Localised, not translated: a pun does not survive translation, so each
 * language has its own file instead of a .po entry, and this one is an
 * English take on the German pool rather than a word-for-word copy. No
 * greeting names a weekday, because the pool applies on every day, and none
 * quotes a film, series or book verbatim.
 *
 * Placeholders in holiday greetings: {hebrew_year}, {unity_years}, {next_year}.
 *
 * @package Baukasten\NewTab
 */

defined( 'ABSPATH' ) || exit;

return array(
	'random'   => array(
		'Hello world. Meeting status: overbooked.',
		'This meeting could have been an email. This page is not.',
		'Do not disturb: synergies are compiling.',
		'Calendar full, coffee empty. Priorities clear.',
		'The sprint begins. The goal will follow.',
		'New update available: Motivation 2.0 (beta).',
		'Scalable, agile, disruptive. And still doing the bare minimum.',
		'All systems running. So is the staff, towards the coffee machine.',
		'Backlog: 412 items. Focus: the coffee.',
		'Quick question that will not be quick.',
		'Please pick up all stakeholders. A map would help.',
		'We have always done it this way. That is why git blame exists.',
		'Quarterly target met, if you round generously.',
		'We will take this offline and discuss it online.',
		'Low-code, high-caffeine.',
		'Your request is important to us. Your ticket: number 1 of 9,841.',
		'KPI green. Nobody knows why.',
		'Roadmap: one arrow pointing up and to the right.',
		'Fail fast, learn faster, commit anyway.',
		'Your inbox: unread as a lifestyle.',
		'Reply-all is not team spirit.',
		'Documentation comes later. It never did.',
		'We meet everyone where they are, and walk to the kitchen together.',
		'The update was not critical. The weekend was.',
		'Everything is under control. The control is a workaround.',
		'Please change your password. Not to Password1!.',
		'Two-factor authentication: because one factor is not an excuse.',
		'This project has been almost done since 2019.',
		'Cloud strategy: it is always raining somewhere.',
		'Say Excel, mean database. Say database, mean Excel.',
		'One slide, four arrows, zero decisions.',
		'Deadlines are a social construct. Deployments, sadly, are not.',
		'Tested a backup today? Untested, it is a wish.',
		'Hotfix in production: bold, fast, undocumented.',
		'Rubber duck debugging meets the retrospective.',
		'We are a startup, we have a foosball table.',
		'Digital transformation: fax machine, now with Wi-Fi.',
		'Everything is stable. That is suspicious.',
		'Compliance check passed. With remarks. Many remarks.',
		'Headcount: 1. Responsibility: everyone.',
		'Your suggestion has been moved to the parking lot. Grass is growing over it.',
		'Single point of failure: the person who knows the script.',
		'Timeboxing: 15 minutes. Experience says 45.',
		'Follow-up later is a strategy too.',
		'We live agility: the plan changes faster than the plan.',
		'Alt+Tab is the only method everyone masters.',
		'Open source, open space, open questions.',
		'Excel is Turing complete. That explains a lot.',
		'Scales until the day somebody uses it.',
		'Best practice: whatever is working for us right now.',
		'No scope creep, just creative scope.',
		'We are on schedule. The schedule just does not know yet.',
	),
	'holidays' => array(
		'carnival_cologne' => 'Kölle Alaaf! Costume update installed successfully.',
		'easter'           => 'Happy Easter! Found all the Easter eggs?',
		'eid_al_fitr'      => 'Eid Mubarak! A blessed feast at the end of Ramadan.',
		'europe_day'       => 'Happy Europe Day! 27 countries, 24 official languages, one roaming plan.',
		'eid_al_adha'      => 'Eid al-Adha Mubarak! A blessed Feast of Sacrifice.',
		'rosh_hashanah'    => 'Shana Tova u-Metuka! A good and sweet year {hebrew_year}.',
		'yom_kippur'       => 'G\'mar Chatima Tova. An easy fast to all who are fasting today.',
		'german_unity'     => 'Happy German Unity Day! One branch for {unity_years} years now.',
		'christmas'        => 'Merry Christmas! Cookie supply: critically high.',
		'new_years_eve'    => 'Happy New Year\'s Eve! Countdown running: year = {next_year}.',
	),
);
