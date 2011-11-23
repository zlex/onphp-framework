<?php
/***************************************************************************
 *   Copyright (C) 2006-2009 by Garmonbozia Research Group                 *
 *   Anton E. Lebedevich, Konstantin V. Arkhipov                           *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU Lesser General Public License as        *
 *   published by the Free Software Foundation; either version 3 of the    *
 *   License, or (at your option) any later version.                       *
 *                                                                         *
 ***************************************************************************/

	/**
	 * Date's container and utilities.
	 * 
	 * @see DateRange
	 * 
	 * @ingroup Base
	**/
	class Date implements Stringable, DialectString
	{
		const WEEKDAY_MONDAY 	= 1;
		const WEEKDAY_TUESDAY	= 2;
		const WEEKDAY_WEDNESDAY	= 3;
		const WEEKDAY_THURSDAY	= 4;
		const WEEKDAY_FRIDAY	= 5;
		const WEEKDAY_SATURDAY	= 6;
		const WEEKDAY_SUNDAY	= 0; // because strftime('%w') is 0 on Sunday
		
		protected $string	= null;
		protected $int		= null;
		
		protected $year		= null;
		protected $month	= null;
		protected $day		= null;
		
		/**
		 * @return Date
		**/
		public static function create($date)
		{
			return new self($date);
		}
		
		public static function today($delimiter = '-')
		{
			return date("Y{$delimiter}m{$delimiter}d");
		}
		
		/**
		 * @return Date
		**/
		public static function makeToday()
		{
			return new self(self::today());
		}
		
		/**
		 * @return Date
		 * @see http://www.faqs.org/rfcs/rfc3339.html
		 * @see http://www.cl.cam.ac.uk/~mgk25/iso-time.html
		**/
		public static function makeFromWeek($weekNumber, $year = null)
		{
			if (!$year)
				$year = date('Y');

			Assert::isTrue(
				($weekNumber > 0)
				&& ($weekNumber <= self::getWeekCountInYear($year))
			);
			
			$date =
				new self(
					date(
						self::getFormat(),
						mktime(
							0, 0, 0, 1, 1, $year
						)
					)
				);
			
			$days =
				(
					(
						$weekNumber - 1
						+ (self::getWeekCountInYear($year - 1) == 53 ? 1 : 0)
					)
					* 7
				) + 1 - $date->getWeekDay();
			
			return $date->modify("+{$days} day");
		}
		
		public static function dayDifference(Date $left, Date $right)
		{
			return
				gregoriantojd(
					$right->getMonth(),
					$right->getDay(),
					$right->getYear()
				)
				- gregoriantojd(
					$left->getMonth(),
					$left->getDay(),
					$left->getYear()
				);
		}
		
		public static function compare(Date $left, Date $right)
		{
			if ($left->int == $right->int)
				return 0;
			else
				return ($left->int > $right->int ? 1 : -1);
		}

		public static function getWeekCountInYear($year)
		{
			return date('W', mktime(0, 0, 0, 12, 31, $year));
		}

		public function __construct($date)
		{
			$this->import($date);
			$this->buildInteger();
		}

		public function  __sleep()
		{
			return array('int');
		}
		
		public function  __wakeup()
		{
			$this->import($this->int);
		}
		
		public function toStamp()
		{
			return $this->int;
		}
		
		public function toDate($delimiter = '-')
		{
			return
				$this->year
				.$delimiter
				.$this->month
				.$delimiter
				.$this->day;
		}
		
		public function getYear()
		{
			return $this->year;
		}

		public function getMonth()
		{
			return $this->month;
		}

		public function getDay()
		{
			return $this->day;
		}
		
		public function getWeek()
		{
			return date('W', $this->int);
		}

		public function getWeekDay()
		{
			return strftime('%w', $this->int);
		}
		
		/**
		 * @return Date
		**/
		public function spawn($modification = null)
		{
			$child = new $this($this->string);
			
			if ($modification)
				return $child->modify($modification);
			
			return $child;
		}
		
		/**
		 * @throws WrongArgumentException
		 * @return Date
		**/
		public function modify($string)
		{
			try {
				$time = strtotime($string, $this->int);
				
				if ($time === false)
					throw new WrongArgumentException(
						"modification yielded false '{$string}'"
					);
				
				$this->int = $this->import($time);
			} catch (BaseException $e) {
				throw new WrongArgumentException(
					"wrong time string '{$string}'"
				);
			}
			
			return $this;
		}
		
		public function getDayStartStamp()
		{
			return
				mktime(
					0, 0, 0,
					$this->month,
					$this->day,
					$this->year
				);
		}
		
		public function getDayEndStamp()
		{
			return
				mktime(
					23, 59, 59,
					$this->month,
					$this->day,
					$this->year
				);
		}
		
		/**
		 * @return Date
		**/
		public function getFirstDayOfWeek($weekStart = Date::WEEKDAY_MONDAY)
		{
			return $this->spawn(
				'-'.((7 + $this->getWeekDay() - $weekStart) % 7).' days'
			);
		}
		
		/**
		 * @return Date
		**/
		public function getLastDayOfWeek($weekStart = Date::WEEKDAY_MONDAY)
		{
			return $this->spawn(
				'+'.((13 - $this->getWeekDay() + $weekStart) % 7).' days'
			);
		}
		
		public function toString()
		{
			return $this->string;
		}
		
		public function toDialectString(Dialect $dialect)
		{
			// there are no known differences yet
			return $dialect->quoteValue($this->toString());
		}
		
		/**
		 * ISO 8601 date string
		**/
		public function toIsoString()
		{
			return $this->toString();
		}
		
		/**
		 * @return Timestamp
		**/
		public function toTimestamp()
		{
			return Timestamp::create($this->toStamp());
		}
		
		protected static function getFormat()
		{
			return 'Y-m-d';
		}
		
		protected function import($string)
		{
			$stamp =
				is_numeric($string)
					? intval($string)
					: $this->stringToStamp($string);
			
			list($this->year, $this->month, $this->day) =
				explode('_', date('Y_m_d', $stamp), 3); // can be with unary minus
			
			if (!checkdate($this->month, $this->day, $this->year)) {
				throw new WrongArgumentException(
					"wrong date format - '{$string}'"
				);
			}
			
			$this->string = date($this->getFormat(), $stamp);
			
			return $stamp;
		}
		
		protected function stringToStamp($string)
		{
			if (
				preg_match(
					'/^(\d{1,4})-(\d{1,2})-(\d{1,2})$/',
					$string,
					$matches
				)
				&& !checkdate($matches[2], $matches[3], $matches[1])
			) {
				throw new WrongArgumentException(
					"wrong date format - '{$string}'"
				);
			}
			
			$stamp = strtotime($string);
			
			if (false === $stamp) {
				throw new WrongArgumentException(
					"strange input given - '{$string}'"
				);
			}
			
			return $stamp;
		}
		
		/* void */ protected function buildInteger()
		{
			$this->int =
				mktime(
					0, 0, 0,
					$this->month,
					$this->day,
					$this->year
				);
		}
	}
?>
