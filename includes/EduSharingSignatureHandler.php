<?php

namespace MediaWiki\Extension\EduSharing;

use EduSharingApiClient\EduSharingNodeHelper;
use EduSharingApiClient\SignatureHandler;
use MediaWiki\MediaWikiServices;

class EduSharingSignatureHandler implements SignatureHandler {

    private EduSharingNodeHelper $nodeHelper;
    /** @var \BagOStuff Cache for storing the signature algorithm */
    private $cache;

    public function __construct(EduSharingNodeHelper $nodeHelper) {
        $this->nodeHelper = $nodeHelper;
        $this->cache      = MediaWikiServices::getInstance()->getMainObjectStash();
    }

    /**
     * Retrieves the signature algorithm used by the repository. If a default signature algorithm
     * is specified in the repository's information, it is cached and returned. Otherwise, a fallback
     * default algorithm is used and cached.
     *
     * @return string The signature algorithm.
     */
    public function getAlgorithm(): string {
        $key = $this->cache->makeKey('edusharing', 'algorithm');
        $algorithm = $this->cache->get( $key );
        if ( !empty( $algorithm )) {
            return $algorithm;
        }
        try {
            error_log("getting about");
            $about = $this->nodeHelper->base->getAbout();
            error_log("got about");
            error_log(json_encode(array_keys($about)));
            if (isset($about['defaultSignatureAlgorithm'])) {
                $this->cache->set($key, $about['defaultSignatureAlgorithm'], 1800);
                return $about['defaultSignatureAlgorithm'];
            }
        } catch (\Throwable) {
            // Do nothing, just use default
        }
        $this->cache->set($key, $this->nodeHelper->base->defaultAlgorithm, 1800);
        return $this->nodeHelper->base->defaultAlgorithm;
    }
}
