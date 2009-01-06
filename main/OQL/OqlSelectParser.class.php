<?php
/****************************************************************************
 *   Copyright (C) 2008 by Vladlen Y. Koshelev                              *
 *                                                                          *
 *   This program is free software; you can redistribute it and/or modify   *
 *   it under the terms of the GNU Lesser General Public License as         *
 *   published by the Free Software Foundation; either version 3 of the     *
 *   License, or (at your option) any later version.                        *
 *                                                                          *
 ****************************************************************************/
/* $Id$ */

	/**
	 * Parses OQL select query.
	 *
	 * Examples:
	 *
	 * from User where id = $1
	 * count(id) as count, count(distinct Name) as distinctCount from User
	 * (id + -$1) / 2 as idExpression, distinct id from User
	 * where (Name not ilike 'user%') and id <= 10 and created between $2 and $3
	 * order by id desc, Name asc
	 * limit 10 offset $2
	 *
	 * from User having $1 > 0 group by id
	 *
	 * @see OQL::select
	 * @see http://www.hibernate.org/hib_docs/reference/en/html/queryhql.html
	 * @see doc/OQL-BNF
	 *
	 * @ingroup OQL
	**/
	final class OqlSelectParser extends OqlParser
	{
		// states
		const PROPERTY_STATE	= 1;
		const FROM_STATE		= 2;
		const WHERE_STATE		= 3;
		const GROUP_BY_STATE	= 4;
		const ORDER_BY_STATE	= 5;
		const HAVING_STATE		= 6;
		const LIMIT_STATE		= 7;
		const OFFSET_STATE		= 8;
		
		// contexts for comma separated lists
		const IN_CONTEXT		= 2;
		const ORDER_BY_CONTEXT	= 4;
		
		// class map
		const HAVING_PROJECTION		= 2;
		
		private static $classMap = array(
			self::HAVING_PROJECTION		=> 'HavingProjection'
		);
		
		/**
		 * @return OqlSelectParser
		**/
		public static function create()
		{
			return new self;
		}
		
		/**
		 * @return OqlSelectQuery
		**/
		protected function makeOqlObject()
		{
			return OqlSelectQuery::create();
		}
		
		protected function handleState()
		{
			switch ($this->state) {
				case self::INITIAL_STATE:
				case self::PROPERTY_STATE:
					return $this->propertyState();
					
				case self::FROM_STATE:
					return $this->fromState();
					
				case self::WHERE_STATE:
					return $this->whereState();
					
				case self::GROUP_BY_STATE:
					return $this->groupByState();
					
				case self::ORDER_BY_STATE:
					return $this->orderByState();
					
				case self::HAVING_STATE:
					return $this->havingState();
					
				case self::LIMIT_STATE:
					return $this->limitState();
					
				case self::OFFSET_STATE:
					return $this->offsetState();
			}
			
			throw new WrongStateException('state machine is broken');
		}
		
		private function propertyState()
		{
			$token = $this->tokenizer->peek();
			
			if (!$token)
				$this->error("expecting 'from' clause");
			
			if ($this->checkKeyword($token, 'from'))
				return self::FROM_STATE;
			
			$this->oqlObject->addProperties(
				OqlSelectPropertiesParser::create()->
					setTokenizer($this->tokenizer)->
					parse()
			);
			
			return self::FROM_STATE;
		}
		
		private function fromState()
		{
			if ($this->checkKeyword($this->tokenizer->peek(), 'from')) {
				$this->tokenizer->next();
				
				$class = $this->tokenizer->next();
				$className = $this->getTokenValue($class, true);
				
				if (
					!$this->checkIdentifier($class)
					|| !ClassUtils::isClassName($className)
				)
					$this->error("invalid class name: {$className}");
				
				if (!class_exists($className, true))
					$this->error("class does not exists: {$className}");
				
				if (!ClassUtils::isInstanceOf($className, 'DAOConnected'))
					$this->error("class must implement DAOConnected interface: {$className}");
				
				$this->oqlObject->setDao(
					call_user_func(array($className, 'dao'))
				);
				
			} else
				$this->error("expecting 'from' clause");
			
			return self::WHERE_STATE;
		}
		
		private function whereState()
		{
			if ($this->checkKeyword($this->tokenizer->peek(), 'where')) {
				$this->tokenizer->next();
				
				$argument = $this->getLogicExpression();
				if ($argument instanceof OqlQueryExpression)
					$this->oqlObject->setWhereExpression($argument);
				else
					$this->error("expecting 'where' expression");
				
				$this->checkParentheses("in 'where' expression");
			}
			
			return self::GROUP_BY_STATE;
		}
		
		private function groupByState()
		{
			if ($this->checkKeyword($this->tokenizer->peek(), 'group by')) {
				$this->tokenizer->next();
				
				$this->oqlObject->addGroupBy(
					OqlSelectGroupByParser::create()->
						setTokenizer($this->tokenizer)->
						parse()
				);
			}
			
			return self::ORDER_BY_STATE;
		}
		
		private function orderByState()
		{
			if ($this->checkKeyword($this->tokenizer->peek(), 'order by')) {
				$this->tokenizer->next();
				
				$list = $this->getCommaSeparatedList(
					self::ORDER_BY_CONTEXT,
					"expecting expression in 'order by'"
				);
				
				foreach ($list as $argument)
					$this->oqlObject->addOrder($argument);
			}
			
			return self::HAVING_STATE;
		}
		
		private function havingState()
		{
			if ($this->checkKeyword($this->tokenizer->peek(), 'having')) {
				$this->tokenizer->next();
				
				if ($argument = $this->getLogicExpression())
					$this->oqlObject->addProjection(
						$this->makeQueryExpression(
							self::$classMap[self::HAVING_PROJECTION],
							$argument
						)
					);
				else
					$this->error("expecting 'having' expression");
				
				$this->checkParentheses("in 'having' expression");
			}
			
			return self::LIMIT_STATE;
		}
		
		private function limitState()
		{
			if ($this->checkKeyword($this->tokenizer->peek(), 'limit')) {
				$this->tokenizer->next();
				
				$token = $this->tokenizer->next();
				if (
					$this->checkToken($token, OqlToken::NUMBER)
					|| $this->checkToken($token, OqlToken::SUBSTITUTION)
				)
					$this->oqlObject->setLimit(
						$this->makeQueryParameter($token)
					);
				
				else
					$this->error("expecting 'limit' expression");
			}
			
			return self::OFFSET_STATE;
		}
		
		private function offsetState()
		{
			if ($this->checkKeyword($this->tokenizer->peek(), 'offset')) {
				$this->tokenizer->next();
				
				$token = $this->tokenizer->next();
				if (
					$this->checkToken($token, OqlToken::NUMBER)
					|| $this->checkToken($token, OqlToken::SUBSTITUTION)
				)
					$this->oqlObject->setOffset(
						$this->makeQueryParameter($token)
					);
				
				else
					$this->error("expecting 'offset' expression");
			}
			
			return self::FINAL_STATE;
		}
		
		/**
		 * @throws SyntaxErrorException
		 * @throws WrongArgumentException
		 * @return OqlQueryParameter
		**/
		protected function getArgumentExpression($context, $message)
		{
			switch ($context) {
				case self::IN_CONTEXT:
					$argument = $this->getConstantExpression();
					break;
				
				case self::ORDER_BY_CONTEXT:
					$expression = $this->getLogicExpression();
					
					$token = $this->tokenizer->peek();
					if ($this->checkKeyword($token, array('asc', 'desc'))) {
						$direction = $token->getValue() == 'asc';
						$this->tokenizer->next();
						
					} else
						$direction = null;
					
					$argument = new OqlOrderByExpression($expression, $direction);
					
					break;
					
				default:
					new WrongArgumentException("unknown context '{$context}'");
			}
			
			return $argument;
		}
	}
?>