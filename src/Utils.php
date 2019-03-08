<?php
Class Utils {

	public static function formatDateTime($dateTime, $format = 'Y-m-d H:i:s') {
		$datetime = strtotime($dateTime);
		return date($format, $datetime);
	}

	public static function validateDateString($date, $format = 'Y-m-d H:i:s') {
        $d = DateTime::createFromFormat($format, $date);
        return ($d && ($d->format($format) == $date));
    }

    public static function getTimeDifferenceInHours($start_time, $end_time) {
    	$start_date = new DateTime($start_time);
		$since_start = $start_date->diff(new DateTime($end_time));
		$hours = $since_start->days * 24;
		$hours += $since_start->h;
		return $hours;
    }

    public static function removeWhitespaces($string) {
    	return str_replace(' ', '', $string);
    }
}