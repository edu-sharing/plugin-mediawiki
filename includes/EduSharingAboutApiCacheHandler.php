<?php
namespace MediaWiki\Extension\EduSharing;

use EduSharingApiClient\AboutApiCacheHandler;
use EduSharingApiClient\EduSharingHelperBase;
use MediaWiki\MediaWikiServices;

/**
 * Caches the repository /rest/_about response in MediaWiki's WAN object cache.
 *
 * The payload is small and identical for every user, so it is cached per
 * repository/app with a TTL to stay fresh in case the connected repository
 * is upgraded.
 */
class EduSharingAboutApiCacheHandler implements AboutApiCacheHandler {
	/** Cache lifetime for the _about response in seconds */
	private const CACHE_TTL = 3600;

	/** @var EduSharingHelperBase Low-level helper used for the live _about call */
	private EduSharingHelperBase $base;

	/**
	 * @param EduSharingHelperBase $base
	 */
	public function __construct( EduSharingHelperBase $base ) {
		$this->base = $base;
	}

	/**
	 * Returns the repository _about response, cached at application level.
	 *
	 * On a cache miss (or after the TTL expires) the live /rest/_about
	 * endpoint is queried once and the result is stored for subsequent calls.
	 * Failures propagate uncached.
	 *
	 * @return array
	 * @throws \JsonException
	 */
	public function getAboutApiCache(): array {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$key = $cache->makeKey(
			'edusharing',
			'about',
			md5( $this->base->baseUrl . '|' . $this->base->appId )
		);

		$about = $cache->get( $key );
		if ( !is_array( $about ) ) {
			$about = $this->base->getAbout();
			$cache->set( $key, $about, self::CACHE_TTL );
		}

		return $about;
	}
}
