<?php
/***************************************************************************
 *   Copyright (C) 2004-2009 by Garmonbozia Research Group,                *
 *   Anton E. Lebedevich, Konstantin V. Arkhipov                           *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/

	/**
	 * Date and time container and utilities.
	 *
	 * @see Date
	 *
	 * @ingroup Base
	**/
	class Timestamp extends Date
	{
		private $hour		= null;
		private $minute		= null;
		private $second		= null;
		
		/**
		 * @return Timestamp
		**/
		public static function create($timestamp)
		{
			return new self($timestamp);
		}
		
		public static function now()
		{
			return date(self::getFormat());
		}
		
		/**
		 * @return Timestamp
		**/
		public static function makeNow()
		{
			return new self(time());
		}
		
		/**
		 * @return Timestamp
		**/
		public static function makeToday()
		{
			return new self(self::today());
		}
		
		public function toTime($timeDelimiter = ':', $secondDelimiter = '.')
		{
			return
				$this->hour
				.$timeDelimiter
				.$this->minute
				.$secondDelimiter
				.$this->second;
		}
		
		public function toDateTime(
			$dateDelimiter = '-',
			$timeDelimiter = ':',
			$secondDelimiter = '.'
		)
		{
			return
				$this->toDate($dateDelimiter).' '
				.$this->toTime($timeDelimiter, $secondDelimiter);
		}
		
		public function getHour()
		{
			return $this->hour;
		}
		
		public function getMinute()
		{
			return $this->minute;
		}
		
		public function getSecond()
		{
			return $this->second;
		}
		
		public function equals(Timestamp $timestamp)
		{
			return ($this->toDateTime() === $timestamp->toDateTime());
		}
		
		public function getDayStartStamp()
		{
			if (!$this->hour && !$this->minute && !$this->second)
				return $this->int;
			else
				return parent::getDayStartStamp();
		}
		
		public function getHourStartStamp()
		{
			if (!$this->minute && !$this->second)
				return $this->int;
			
			return
				mktime(
					$this->hour,
					0,
					0,
					$this->month,
					$this->day,
					$this->year
				);
		}
		
		/**
		 * ISO 8601 time string
		**/
		public function toIsoString($convertToUtc = true)
		{
			return
				$convertToUtc
					? date('Y-m-d\TH:i:s\Z', $this->int - date('Z', $this->int))
					: date('Y-m-d\TH:i:sO', $this->int);
		}
		
		/**
		 * @return Timestamp
		**/
		public function toTimestamp()
		{
			return $this;
		}
		
		protected static function getFormat()
		{
			return 'Y-m-d H:i:s';
		}
		
		protected function import($string)
		{
			$stamp = parent::import($string);
			
			list($this->hour, $this->minute, $this->second) =
				explode(':', date('H:i:s', $stamp), 3);
			
			return $stamp;
		}
		
		protected function stringToStamp($string)
		{
			if (
				preg_match(
					'/^(\d{1,4})-(\d{1,2})-(\d{1,2})\s\d{1,2}:\d{1,2}:\d{1,2}$/',
					$string,
					$matches
				)
				&& !checkdate($matches[2], $matches[3], $matches[1])
			) {
				throw new WrongArgumentException(
					"wrong date format - '{$string}'"
				);
			}
			
			return parent::stringToStamp($string);
		}
		
		/* void */ protected function buildInteger()
		{
			$this->int =
				mktime(
					$this->hour,
					$this->minute,
					$this->second,
					$this->month,
					$this->day,
					$this->year
				);
		}
	}
?>