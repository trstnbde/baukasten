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
		'Hello world. System status: operational.',
		'There is no place like 127.0.0.1.',
		'Have you tried turning it off and on again?',
		'The answer is 42. The question is still pending.',
		'Beware of Heisenbugs: they vanish the moment you look.',
		'New day, new save point.',
		'Remember to quicksave.',
		'Critical hit on the to-do list!',
		'Dice roll of the day: a natural 20.',
		'Achievement unlocked: opened the browser.',
		'Mana full, coffee too.',
		'Today\'s final boss: the inbox.',
		'Side quest available: clean out the fridge.',
		'Loading level 2. Please do not switch off.',
		'Entered the Konami code? Off you go.',
		'Insert coin to continue.',
		'Teleporter calibrated. Destination: end of the working day.',
		'Warp drive standing by, Captain.',
		'Houston, we have no problem.',
		'Beam me up. After the coffee.',
		'Packed your towel? Then nothing can go wrong.',
		'Second breakfast counts as a meal.',
		'Time travel successful: you have arrived in the present.',
		'News from outer space: still no word from the aliens.',
		'The universe is expanding. Hopefully your to-do list is not.',
		'Schrödinger\'s inbox: read and unread at the same time.',
		'Entropy is rising. Tidying up is still worth it.',
		'Light does 299,792,458 m/s. Your Wi-Fi does a little less.',
		'Pi is irrational. Your plan for today is not.',
		'Calibrated, compiled, caffeinated.',
		'Soldering iron hot, ideas hotter.',
		'Measure first, then solder.',
		'The 3D printer is running. Hopefully your patience is too.',
		'Raspberry Pi or raspberry pie? Why not both.',
		'Board game night? The dice are ready.',
		'Retro mode: 8 bits, full spirits.',
		'Floppy inserted. Drive A: ready.',
		'Pixel by pixel to the goal.',
		'It is not DNS. It cannot be DNS. It was DNS.',
		'Not a bug. An undocumented feature.',
		'To understand recursion, you must first understand recursion.',
		'There are 10 kinds of people: those who understand binary and those who do not.',
		'Ctrl+Z for real life would be a great feature.',
		'No backup, no sympathy.',
		'Tabs or spaces? No debates on principle today.',
		'HTTP 418: I am a teapot.',
		'404: Procrastination not found.',
		'sudo make me a coffee.',
		'Rubber duck ready. Explain your problem to it.',
		'ping 127.0.0.1 – pong. You are alive.',
		'Hack the planet. Or the email first.',
		'Next stop: inbox zero.',
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
