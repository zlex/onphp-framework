<?
/***************************************************************************
 *   Copyright (C) 2007 by Ivan Y. Khvostishkov                            *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */
	
	class SessionServer extends Singleton implements Instantiatable
	{
		private $locations	= null;
		private $timeout	= null;
		
		private $actionPages = array();
		
		/**
		 * @return SessionServer
		**/
		public static function me()
		{
			return Singleton::getInstance(__CLASS__);
		}
		
		/**
		 * @return SessionServer
		**/
		public function setLocations(LocationSettings $locations)
		{
			$this->locations = $locations;
			
			return $this;
		}
		
		public function getLocationSettings()
		{
			return $this->locations;
		}
		
		/**
		 * @return SessionServer
		**/
		public function setTimeout($timeout)
		{
			$this->timeout = $timeout;
			
			return $this;
		}
		
		public function getTimeout()
		{
			return $this->timeout;
		}
		
		public function getUrl()
		{
			return $this->locations->getSoap()->getUrl();
		}
		
		public function getRegistrationUrl()
		{
			return $this->getPageUrl(SessionServerUrlSettings::REGISTRATION);
		}
		
		public function getProfileUrl()
		{
			return $this->getPageUrl(SessionServerUrlSettings::PROFILE);
		}
		
		public function getLoginUrl()
		{
			return $this->getPageUrl(SessionServerUrlSettings::LOGIN);
		}
		
		public function getLogoutUrl()
		{
			return $this->getPageUrl(SessionServerUrlSettings::LOGOUT);
		}
		
		/**
		 * @return SessionServer
		**/
		public function setWapActionPages(SessionServerUrlSettings $pages)
		{
			return $this->setActionPages(LocationSettings::WAP, $pages);
		}
		
		/**
		 * @return SessionServer
		**/
		public function setWebActionPages(SessionServerUrlSettings $pages)
		{
			return $this->setActionPages(LocationSettings::WEB, $pages);
		}
		
		/**
		 * @return SessionServer
		**/
		protected function setActionPages($area, SessionServerUrlSettings $pages)
		{
			$this->actionPages[$area] = $pages;
			
			return $this;
		}
		
		protected function getActionPages($area)
		{
			if (!isset($this->actionPages[$area]))
				throw new WrongArgumentException("actionPages for {{$area}} does not defined");
			
			return $this->actionPages[$area];
		}
		
		protected function getPageUrl($action)
		{
			return
				$this->locations->
					get(Application::me()->getLocationArea())->
						getBaseUrl()
				.(
					$this->
						getActionPages(Application::me()->getLocationArea())->
							getPage($action)
				);
		}
	}
?>