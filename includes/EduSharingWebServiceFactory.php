<?php

/*
 * Copyright (C) 2005 Alfresco, Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

 * As a special exception to the terms and conditions of version 2.0 of
 * the GPL, you may redistribute this Program in connection with Free/Libre
 * and Open Source Software ("FLOSS") applications as described in Alfresco's
 * FLOSS exception.  You should have recieved a copy of the text describing
 * the FLOSS exception, and it is also available here:
 * http://www.alfresco.com/legal/licensing"
 */


require_once 'EduSharingWebService.php';

/**
 * Provides access to certain edu-sharing webservices.
 * Therefore methods return soap clients with end points specified by parameters $path and where necessary $ticket.
 */
class edusharingWebServiceFactory  {
    
    /*+
     * Returns client for getting a usage
     * 
     * @param string $path
     * @return object edusharingWebService
     */
	public static function getUsage($path){
		$path .= '/usage?wsdl';
		return new edusharingWebService($path, array());
	}

    /*+
     * Returns client for authentication service
     * 
     * @param string $path
     * @return object edusharingWebService
     */
   public static function getAuthenticationService($path) {
        $path .= '/authentication?wsdl';
        return new edusharingWebService($path, array());
   }

    /*+
     * Returns client for ??? service
     * 
     * @param string $path
     * @param string $ticket
     * @return object edusharingWebService
     */
   public static function getCCCrudService($path, $ticket) {
        $path .= '/crud?wsdl';
        return new edusharingWebService($path, array(), $ticket);
   }

    /*+
     * Returns client for alfresco service
     * 
     * @param string $path
     * @param string $ticket
     * @return object edusharingWebService
     */
   public static function getAlfrescoService($path, $ticket) {
        $path .= '/NativeAlfrescoWrapper?wsdl';
        return new edusharingWebService($path, array(), $ticket);
   }
}

?>