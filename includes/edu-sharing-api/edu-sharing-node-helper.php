<?php
require_once "edu-sharing-helper-abstract.php";

class DisplayMode {
    const Inline = 'inline';
    const Embed = 'embed';
    const Dynamic = 'dynamic';
}
class Usage {
    public $nodeId;
    public $nodeVersion;
    public $containerId;
    public $resourceId;
    public $usageId;

    public function __construct($nodeId, $nodeVersion, $containerId, $resourceId, $usageId)
    {
        $this->nodeId = $nodeId;
        $this->nodeVersion = $nodeVersion;
        $this->containerId = $containerId;
        $this->resourceId = $resourceId;
        $this->usageId = $usageId;
    }

}
class EduSharingNodeHelper extends EduSharingHelperAbstract  {
    /**
     * creates a usage for a given node
     * The given usage can later be used to fetch this node REGARDLESS of the actual user
     * The usage gives permanent access to this node and acts similar to a license
     * In order to be able to create an usage for a node, the current user (provided via the ticket)
     * MUST have CC_PUBLISH permissions on the given node id
     * @param string $ticket
     * A ticket with the user session who is creating this usage
     * @param string $containerId
     * A unique page / course id this usage refers to inside your system (e.g. a database id of the page you include the usage)
     * @param string $resourceId
     * The individual resource id on the current page or course this object refers to
     * (you may enumerate or use unique UUID's)
     * @param string $nodeId
     * The edu-sharing node id the usage shall be created for
     * @param string|null $nodeVersion
     * Optional: The fixed version this usage should refer to
     * If you leave it empty, the usage will always refer to the latest version of the node
     * @return Usage
     * An usage element you can use with @getNodeByUsage
     * Keep all data of this object stored inside your system!
     */
    public function createUsage(
        string $ticket,
        string $containerId,
        string $resourceId,
        string $nodeId,
        string $nodeVersion = null
    ) {
        $curl = curl_init($this->base->baseUrl . '/rest/usage/v1/usages/repository/-home-');
        $headers = $this->getSignatureHeaders($ticket);
        $headers[] = $this->getRESTAuthenticationHeader($ticket);
        curl_setopt_array($curl, [
            CURLOPT_FAILONERROR => false,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode([
                'appId' => $this->base->appId,
                'courseId' => $containerId,
                'resourceId' => $resourceId,
                'nodeId' => $nodeId,
                'nodeVersion' => $nodeVersion,
            ]),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $data = json_decode(curl_exec($curl), true);
        $err     = curl_errno( $curl );
        $info = curl_getinfo($curl);
        curl_close($curl);
        if ($err === 0 && $info["http_code"] === 200) {
            return new Usage(
                $data['parentNodeId'],
                $nodeVersion,
                $containerId,
                $resourceId,
                $data['nodeId']
            );
        } else {
            throw new Exception('creating usage failed ' .
                $info["http_code"] . ': ' . $data['error'] . ' ' . $data['message']);
        }

    }

    /**
     * Loads the edu-sharing node refered by a given usage
     * @param Usage $usage
     * The usage, as previously returned by @createUsage
     * @param string $displayMode
     * The displayMode
     * This will ONLY change the content representation inside the "detailsSnippet" return value
     * @param array $renderingParams
     * @return mixed
     * Returns an object containing a "detailsSnippet" repesentation
     * as well as the full node as provided by the REST API
     * Please refer to the edu-sharing REST documentation for more details
     * @throws Exception
     */
    public function getNodeByUsage(
        Usage $usage,
        $displayMode = DisplayMode::Inline,
        array $renderingParams = null
    )
    {
        $url = $this->base->baseUrl . '/rest/rendering/v1/details/-home-/' . rawurldecode($usage->nodeId);
        $url .= '?displayMode=' . rawurlencode($displayMode);
        if($usage->nodeVersion) {
            $url .= '&version=' . rawurlencode($usage->nodeVersion);
        }
        $curl = curl_init($url);

        $headers = $this->getSignatureHeaders($usage->usageId);
        $headers[] = 'X-Edu-Usage-Node-Id: ' . $usage->nodeId;
        $headers[] = 'X-Edu-Usage-Course-Id: ' . $usage->containerId;
        $headers[] = 'X-Edu-Usage-Resource-Id: ' . $usage->resourceId;

        curl_setopt_array($curl, [
            CURLOPT_FAILONERROR => false,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($renderingParams),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $data = json_decode(curl_exec($curl), true);
        $err     = curl_errno( $curl );
        $info = curl_getinfo($curl);
        if ($err === 0 && $info["http_code"] === 200) {
            return $data;
        } else {
            throw new Exception('fetching node by usage failed ' .
                $info["http_code"] . ': ' . $data['error'] . ' ' . $data['message']);
        }
    }
}