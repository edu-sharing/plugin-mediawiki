<?php


require_once 'EduSharingWebServiceFactory.php';
require_once 'RenderParameter.php';

class EduSharingWS {

    private $edu_webservice;
    private $edu_authentication_wsdl;
    private $edu_usage_wsdl;
    private $edu_alfwrapper_wsdl;
    private $es;
    private $User;
    private $HomePropArray;

    public function __construct() {

        global $wgUser;

        $this -> User = $wgUser;
        $this -> es = new ESApp();
        $this -> es -> loadApps();

        $conf = $this -> es -> getHomeConf();
        $this -> HomePropArray = $conf -> prop_array;

        $this -> edu_webservice = $this -> HomePropArray['edu-webservice'];
        $this -> edu_authentication_wsdl = $this -> HomePropArray['edu-authentication_wsdl'];
        $this -> edu_usage_wsdl = $this -> HomePropArray['edu-usage_wsdl'];
        $this -> edu_alfwrapper_wsdl = $this -> HomePropArray['edu-alfwrapper_wsdl'];

    }

    public function getConfigs() {
        return $this -> es;
    }

    public function getHomeConfig() {
        return $this -> HomePropArray;
    }

    public function getEduWebservice() {
        return $this -> edu_webservice;
    }

    public function getAuthentication_wsdl() {
        return $this -> edu_authentication_wsdl;
    }

    private function getUsage_wsdl() {
        return $this -> edu_usage_wsdl;
    }

    private function getAlfresco_wsdl() {
        return $this -> edu_alfwrapper_wsdl;
    }

    // --- ---
    public function getAuthentication() {

        $cUrl = $this -> getEduWebservice();
        $cPath = $this -> getAuthentication_wsdl();

        return new edusharingWebService($cUrl . $cPath, array());
    }// eof getCCAuthentication

    public function getUsage() {

        $cUrl = $this -> getEduWebservice();
        $cPath = $this -> getUsage_wsdl();

        return new edusharingWebService($cUrl . $cPath, array());
    }// eof getCCAuthentication

    public function getAlfrescoService($ticket) {

        $cUrl = $this -> getEduWebservice();
        $cPath = $this -> getAlfresco_wsdl();

        return new edusharingWebService($cUrl . $cPath, array(), $ticket);
    }// eof getCCAuthentication

    public function addUsage($edu_sharing) {

        $data4xml = array("ccrender");
        $data4xml[1]["ccuser"]["id"] = trim(strtolower($this -> User -> getName()));
        $data4xml[1]["ccuser"]["name"] = trim(strtolower($this -> User -> getName()));
        $data4xml[1]["ccserver"]["ip"] = $_SERVER['SERVER_ADDR'];
        $data4xml[1]["ccserver"]["hostname"] = $_SERVER['SERVER_NAME'];
        $data4xml[1]["ccserver"]["mnet_localhost_id"] = $this -> HomePropArray['appid'];

        // loop trough the list of keys... get the value... put into XML
        $keyList = array('resizable', 'scrollbars', 'directories', 'location', 'menubar', 'toolbar', 'status', 'width', 'height');
        foreach ($keyList as $key) {
            $data4xml[1]["ccwindow"][$key] = isSet($l_resource_cfg[$key]) ? $l_resource_cfg[$key] : 0;
        }

        $data4xml[1]["ccwindow"]["forcepopup"] = isSet($l_resource_cfg['windowpopup']) ? 1 : 0;
        $data4xml[1]["ccdownload"]["download"] = isSet($l_resource_cfg['forcedownload']) ? 1 : 0;
        $data4xml[1]["ccversion"]["version"] = ($edu_sharing -> versionShow == 'latest') ? 1 : 0;

        //$node_version = $edu_sharing -> version;
        $node_version = ($edu_sharing -> versionShow == 'latest')? 0 : $edu_sharing -> version;

        $data4xml[1]["ccreferencen"]["reference"] = $resRef;

        $myXML = new RenderParameter();
        $xml = $myXML -> getXML($data4xml);

        //usage2
        $params = array(
            "eduRef" => $edu_sharing -> id,
            "user" => $edu_sharing -> user,
            "lmsId" => $edu_sharing -> appid,
            "courseId" => $edu_sharing -> pageid,
            "userMail" => $edu_sharing -> user,
            "fromUsed" => '2002-05-30T09:00:00',
            "toUsed" => '2222-05-30T09:00:00',
            "distinctPersons" => '0',
            "version" => $node_version,
            "resourceId" => $edu_sharing -> resourceid,
            "xmlParams" => $xml,
        );

        try {
            $ccwsusage = $this -> getUsage();

        } catch(SoapFault $e) {
            echo "<pre>";
            echo 'SoapFault' . $e -> faultstring;
        }

        try {
            $_wsUsage = $ccwsusage -> setUsage($params);
        } catch(SoapFault $e) {
            echo "<pre>";
            echo 'SoapFault' . $e -> faultstring;
        }

    }

    public function delUsage($params) {
        try {
            $cUrl = $this -> getEduWebservice();
            $cPath = $this -> getUsage_wsdl();
            $ccwsusage = $this -> getUsage();
            $ccwsusage -> deleteUsage($params);
        } catch(SoapFault $e) {
            echo "<pre>";
            echo 'SoapFault' . $e -> faultstring;
        }
    }

    public function getTicket() {

        try {
            $eduservice = $this -> getAuthentication();

            if(empty($this -> User) || filter_var($this -> User -> getName(), FILTER_VALIDATE_IP) !== false)
                $userid = 'mw_guest';
            else
                $userid = trim(strtolower($this -> User -> getName()));

            if (isset($_SESSION["repository_ticket"])) {
                // ticket available.. is it valid?
                $params = array("userid" => $userid, "ticket" => $_SESSION["repository_ticket"]);
                try {
                    $alfReturn = $eduservice -> checkTicket($params);

                    if ($alfReturn === true) {

                        return $_SESSION["repository_ticket"];
                    }
                } catch (Exception $e) {
                    return $e;
                }
            }


            $paramsTrusted = array("applicationId" => $this -> HomePropArray['appid'], "ticket" => session_id(), "ssoData" => array(array('key' => 'userid','value' => $userid)));

            $alfReturn = $eduservice -> authenticateByTrustedApp($paramsTrusted);
            $ticket = $alfReturn -> authenticateByTrustedAppReturn -> ticket;

            $_SESSION["repository_ticket"] = $ticket;
            return $ticket;


        } catch (Exception $e) {
            error_log('Error getting ticket in ' . get_class($this));
            return;

        }
    }
}
?>