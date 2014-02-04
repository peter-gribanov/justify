#!/usr/bin/env php
<?php
/**
 * Justify
 *
 * @package   Justify
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2014, Peter Gribanov
 */

/**
 * For execute program needs PHP interpreter
 *
 * Example insatll:
 * $ apt-get install php5
 *
 * Accepts parameters:
 *   1. Input filename
 *   2. Output filename
 * 
 * Exmple:
 * $ justify.php input.txt output.txt
 */


// line width
define('LINE_WIDTH_MIN', 20);
define('LINE_WIDTH_MAX', 120);
define('LINE_WIDTH_HEADER', 60);
define('PARAGRAPHS_END', '.!&:;');
define('CHARSET', 'UTF-8');


// check args
if (empty($argv[1]) || empty($argv[2])) {
	throw new \Exception('Unknown the input or output file');
}

// check input file
$input_file = $argv[1];
if (!file_exists($input_file)) {
	throw new \Exception('Input file is not found');
}
if (!is_readable($input_file)) {
	throw new \Exception('Input file is not readable');
}

// check output file
$output_file = $argv[2];
if (!is_writable(pathinfo($output_file, PATHINFO_DIRNAME))) {
	throw new \Exception('Can not create output file');
}


/**
 * Fill string
 *
 * @param string $line
 *
 * @return string
 */
function fill($string)
{
	$words = explode(' ', $string); // list words
	$count = count($words) - 1;
	$length = mb_strlen($string, CHARSET);
	$add = floor((LINE_WIDTH_MAX - $length - 1) / $count); // even the number of indents
	$indent = str_repeat(' ', $add); // spaces
	$spec_add = LINE_WIDTH_MAX - (($add * $count) + $length) - 1; // additional odd number of indents

	// add indents
	for ($i = 0; $i < $count; $i++) {
		$words[$i] .= $indent.' ';

		// add additional indent if can
		if ($spec_add) {
			$words[$i] .= ' ';
			$spec_add--;
		}
	}
	return implode('', $words)."\n";
}


$input = file_get_contents($input_file); // read text
// clear text
$input = str_replace("\r\n", "\n", trim($input));
$input = preg_replace('/[\t| ]+/', ' ', $input);

// drafting paragraphs of lines
$output = '';
$last = '';
while ($input) {
	// get line
	if (($pos = mb_strpos($input, "\n", 0, CHARSET)) !== false) {
		$line = trim(mb_substr($input, 0, $pos, CHARSET));
		$input = mb_substr($input, $pos+1, mb_strlen($input, CHARSET)-$pos-1, CHARSET);
	} else {
		// last paragraph
		$line = $input;
		$input = '';
	}

	// add line to paragraph
	if ($line && mb_strpos(PARAGRAPHS_END, $line[mb_strlen($line, CHARSET)-1], 0, CHARSET) === false) {
		$last .= ($last ? ' ' : '').$line;

	} else {
		$paragraph = $last.(($line && $last) ? ' ' : '').$line;

		if (
			!$paragraph || // empty line
			mb_strlen($paragraph, CHARSET) < LINE_WIDTH_HEADER || // header
			mb_strlen($paragraph, CHARSET) < LINE_WIDTH_MAX // single-paragraph
		) {
			$output .= $paragraph."\n";

		} else { // format paragraph
			// distribution of words in a paragraph on the line
			$length = 0;
			$line_num = 0;
			$lines = array();
			$words = explode(' ', $paragraph);
			while ($word = array_shift($words)) {
				$word_length = mb_strlen($word, CHARSET);
				if ($length + $word_length + 1 < LINE_WIDTH_MAX) {
					if (isset($lines[$line_num])) {
						$lines[$line_num] .= ' '.$word;
						$length += $word_length + 1;
					} else {
						$lines[$line_num] = $word;
						$length = $word_length;
					}
				} else { // new line
					$lines[++$line_num] = $word;
					$length = $word_length;
				}
			}

			// if necessary, extend the last lines
			while ($line_num != 0) {
				while (mb_strlen($lines[$line_num], CHARSET) < LINE_WIDTH_MIN) {
					// transfer the last word from the previous line
					$length = mb_strlen($lines[$line_num - 1], CHARSET);
					$pos = mb_strrpos($lines[$line_num - 1], ' ');
					$last_word = mb_substr($lines[$line_num - 1], $pos + 1, $length - $pos, CHARSET);
					$lines[$line_num - 1] = mb_substr($lines[$line_num - 1], 0, $pos, CHARSET);
					$lines[$line_num] = $last_word.' '.$lines[$line_num];
				}
				$line_num--;
			}

			// fill lines
			for ($i = 0; $i < count($lines) - 1; $i++) {
				$output .= fill($lines[$i]);
			}
			$output .= $lines[$i]."\n";

			unset($lines, $words);
		}
		$last = '';
	}
}

// write output
file_put_contents($output_file, trim($output));