<?php
/**
 * Hooks for EduSharing extension
 *
 * @file
 * @ingroup Extensions
 */

/**
 * EduSharing hooks
 */
namespace MediaWiki\Extension\EduSharing;

use EduSharingApiClient\Usage;
use ManualLogEntry;
use MediaWiki\Config\Config;
use MediaWiki\Content\TextContent;
use MediaWiki\Json\FormatJson;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Parser;
use StatusValue;

class EduSharingHooks implements
	\MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook,
	\MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook,
	\MediaWiki\Hook\ParserFirstCallInitHook,
	\MediaWiki\Hook\ParserPreSaveTransformCompleteHook,
	\MediaWiki\Page\Hook\PageDeleteHook,
	\MediaWiki\Page\Hook\PageUndeleteCompleteHook,
	\MediaWiki\Output\Hook\BeforePageDisplayHook,
	\MediaWiki\Storage\Hook\PageSaveCompleteHook
{

	/** @var array<string,int[]> pageKey => [resourceId, ...] */
	private static array $pendingResourceIds = [];

	/** @inheritDoc */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		# TODO: move static js config here from self::onMakeGlobalVariablesScript()
	}

	/**
	 * Adds edu-sharing item to editor toolbar
	 */

	/** @inheritDoc */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		$user    = $out->getUser();
		$services = MediaWikiServices::getInstance();
		$mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

		$eduService = new EduSharingService( $user, $mwConfig );
		$ticket = $eduService->getTicket() ?? '';

		global $wgServer, $wgScriptPath;

		$out->addModules( 'ext.eduSharing.dialog' );
		$out->addJsConfigVars( [ 'eduticket' => $ticket ] );
		$out->addJsConfigVars( [ 'eduusername' => $eduService->config->username ] );
		$out->addJsConfigVars( [ 'eduappid' => $eduService->config->appId ] );
		$out->addJsConfigVars( [
			'edugui' => $eduService->config->baseUrl .
				'/components/search?ticket=' . $ticket . '&reurl=WINDOW'
		] );

		$out->addJsConfigVars( [ 'edu_preview_icon_video' => $eduService->config->iconMimeVideo ] );
		$out->addJsConfigVars( [ 'edu_preview_icon_audio' => $eduService->config->iconMimeAudio ] );
		$out->addJsConfigVars( [ 'edupreview' => $eduService->config->baseUrl . '/preview?' ] );
		$out->addJsConfigVars( [
			'eduicon' => $wgServer . $wgScriptPath .
				'/extensions/EduSharing/resources/images/edu-icon.svg'
		] );
	}

	/**
	 * Parses $xml for edutags
	 * @param string $tag
	 * @param string $xml
	 * @return array $matches
	 */
	public static function getEduTags( $tag, $xml ) {
		$tag = preg_quote( $tag, '#' );
		// Match only exact tag names (e.g. <edusharing ...>), not prefixed variants
		// like <edusharing-widget ...>.
		$pattern = '#<' . $tag . '(?=[\\s/>])[^>]*?(?:/>|>.*?</' . $tag . '\\s*>)#is';
		preg_match_all( $pattern, $xml, $matches, PREG_PATTERN_ORDER );
		$tags = $matches[0] ?? [];

		// Return both the raw match (as it appears in the text) and a normalized version
		// that can be parsed by simplexml (expand self-closing tags).
		$result = [];
		foreach ( $tags as $t ) {
			$normalized = $t;
			if ( str_ends_with( trim( $t ), '/>' ) ) {
				$normalized = preg_replace(
					'/<(' . $tag . '\\b[^>]*)\\/>/i',
					'<$1></' . $tag . '>',
					$t
				);
			}
			$result[] = [
				'raw'        => $t,
				'normalized' => $normalized
			];
		}

		return $result;
	}

	/**
	 * Delete a resource record locally and remove usage in the repository.
	 *
	 * @param EduSharingService $eduService
	 * @param \stdClass $resource
	 * @return void
	 */
	private static function deleteResourceAndUsage( EduSharingService $eduService, $resource ) {
		/*
		* Delete record in db
		*/
		$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbw = $dbProvider->getPrimaryDatabase();

		$dbw->delete(
			'edusharing_resource',
			[ 'EDUSHARING_RESOURCE_ID = ' . $resource->EDUSHARING_RESOURCE_ID ],
			$fname = 'Database::delete'
		);

		$postData           = new \stdClass();
		$postData->nodeId   = str_replace( "ccrep://local/", "", $resource->EDUSHARING_RESOURCE_OBJECT_URL );
		$postData->usageId  = $resource->EDUSHARING_RESOURCE_USAGE;

		// delete usage from repo
		$eduService->deleteUsage( $postData );
	}

	/**
	 * Insert a resource record and create usage in the repository.
	 *
	 * @param EduSharingService $eduService
	 * @param array $resourceData
	 * @param bool $isRestore
	 * @return mixed Usage object
	 */
	private static function addResourceAndUsage(
		EduSharingService $eduService,
		$resourceData,
		bool $isRestore = false
	) {
		// if we don't restore a previously deleted resource, we don't want to re-use an existing id
		if ( $isRestore !== true ) {
			unset( $resourceData[ 'EDUSHARING_RESOURCE_ID' ] );
		}

		$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbw = $dbProvider->getPrimaryDatabase();

		$dbw->insert( 'edusharing_resource', $resourceData, 'Database::insert' );
		$resourceId = $dbw->insertId();

		$postData = new \stdClass();

		$postData->ticket       = $eduService->getTicket();
		$postData->containerId  = (
			$resourceData[ 'EDUSHARING_RESOURCE_PAGE_ID' ] === null
				? 0
				: $resourceData[ 'EDUSHARING_RESOURCE_PAGE_ID' ]
		);
		$postData->resourceId   = $resourceId;
			$postData->nodeId       = str_replace(
				"ccrep://local/",
				"",
				$resourceData[ 'EDUSHARING_RESOURCE_OBJECT_URL' ]
			);

		$usage = $eduService->createUsage( $postData );

		if ( $usage ) {
			$dbw->update(
				'edusharing_resource',
				[ 'EDUSHARING_RESOURCE_USAGE' => $usage->usageId ],
				[ 'EDUSHARING_RESOURCE_ID' => $resourceId ],
				'Database::update'
			);
		}

		return $usage;
	}

	/**
	 * Deletes usages for edu-sharing resources on article delete.
	 *
	 * @param ProperPageIdentity $page
	 * @param Authority $deleter
	 * @param string $reason
	 * @param StatusValue $status
	 * @param bool $suppress
	 * @return bool
	 */
	public function onPageDelete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		StatusValue $status,
		bool $suppress
	) {
		/*
		 * Select edu-sharing resources of the article that will be deleted
		 */
		$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbr = $dbProvider->getReplicaDatabase();

		$res = $dbr->select( 'edusharing_resource',
			[
				'EDUSHARING_RESOURCE_ID',
				'EDUSHARING_RESOURCE_USAGE',
				'EDUSHARING_RESOURCE_OBJECT_URL'
			], // $vars (columns of the table)
			'EDUSHARING_RESOURCE_PAGE_ID = ' . $page->getId(),
			'Database::select',
			[ 'ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC' ]
		);

		/*
		 * Delete usages for edusharing resources
		 */
		$user = $deleter->getUser();
		$services = MediaWikiServices::getInstance();
		$mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

		$eduService = new EduSharingService( $user, $mwConfig );
		if ( !$eduService->isAvailable ) {
			return true;
		}
		foreach ( $res as $resource ) {
			self::deleteResourceAndUsage( $eduService, $resource );
		}

		return true;
	}

	/**
	 * Adds usages for edu-sharing resources on article undelete.
	 *
	 * @param ProperPageIdentity $pageIdentity
	 * @param Authority $restorer
	 * @param string $reason
	 * @param RevisionRecord $restoredRev
	 * @param ManualLogEntry $logEntry
	 * @param int $restoredRevisionCount
	 * @param bool $created
	 * @param array $restoredPageIds
	 * @return void
	 */
	public function onPageUndeleteComplete(
		ProperPageIdentity $pageIdentity,
		Authority $restorer,
		string $reason,
		RevisionRecord $restoredRev,
		ManualLogEntry $logEntry,
		int $restoredRevisionCount,
		bool $created,
		array $restoredPageIds
	): void {
		$content = $restoredRev->getContent( SlotRecord::MAIN );
		if ( !$content ) {
			error_log(
				'No content found for page ' . $pageIdentity->__toString() .
				' with revision: ' . $restoredRev->getId()
			);
			return;
		}

		if ( $content instanceof TextContent ) {
			$text = $content->getText();
		} else {
			error_log(
				'No Text Content for page ' . $pageIdentity->__toString() .
				' with revision: ' . $restoredRev->getId()
			);
			return;
		}

		$user = $restorer->getUser();
		$services = MediaWikiServices::getInstance();
		$mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

		$eduService = new EduSharingService( $user, $mwConfig );
		if ( !$eduService->isAvailable ) {
			return;
		}
		self::syncArticleResources( $eduService, $pageIdentity, $text, true );
	}

	/**
	 * Parse article text for edusharing tags and sync database resources/usages.
	 *
	 * @param EduSharingService $eduService
	 * @param PageReference|ProperPageIdentity $pageRef
	 * @param string &$text
	 * @param bool $isRestore
	 * @return array Resource IDs needing pageId completion
	 */
	private static function syncArticleResources(
		EduSharingService $eduService,
		PageReference|ProperPageIdentity $pageRef,
		string &$text,
		bool $isRestore
	): array {
		$resourceIds = [];
		if ( !$eduService->isAvailable ) {
			return $resourceIds;
		}
		$old_list = [];
		$pageId = null;
		/*
		 * Select all article's resources
		 */
		$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbr = $dbProvider->getReplicaDatabase();

		if ( $pageRef instanceof ProperPageIdentity ) {
			// if we have a PageIdentity object, we are in undelete context and have a pageId
			$pageId = $pageRef->getId();
		} else {
			// otherwise we come from creating/editing a page and may or may not have a pageId
			// to check we create a title object from the PageReference and and have a look at its articleId.
			// If 0, the page is new and we don't have a pageId and can't use it right now
			$title = MediaWikiServices::getInstance()
				->getTitleFactory()
				->newFromPageReference( $pageRef );

			$articleId = $title->getArticleID();
			$pageId = ( $articleId > 0 ? $articleId : null );
		}
		if ( $pageId !== null ) {
				$res = $dbr->select(
					'edusharing_resource',
					[
						'EDUSHARING_RESOURCE_ID',
						'EDUSHARING_RESOURCE_USAGE',
						'EDUSHARING_RESOURCE_OBJECT_URL'
					], // $vars (columns of the table)
					'EDUSHARING_RESOURCE_PAGE_ID = ' . $pageId, // $conds
					'Database::select', // $fname = 'Database::select',
					[ 'ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC' ] // $options = array()
				);

			foreach ( $res as $row ) {
				$old_list[$row->EDUSHARING_RESOURCE_ID] = $row;
			}
		}

		/*
		 * Get edu-sharing tags from $text
		 */
		$matches = self::getEduTags( 'edusharing', $text );

		/*
		 * For each resource found in text
		 */
		foreach ( $matches as $match ) {
			$edutagOriginal   = $match['raw'];
			$edutagNormalized = $match['normalized'];

			libxml_use_internal_errors( true );
			$Response = simplexml_load_string( $edutagNormalized );
			if ( $Response === false ) {
				// Skip malformed tag to avoid fatal errors
				libxml_clear_errors();
				continue;
			}
			libxml_clear_errors();

			$resourceData = [
				'EDUSHARING_RESOURCE_ID' => (string)$Response['resourceid'],
				'EDUSHARING_RESOURCE_PAGE_ID' => $pageId,
				'EDUSHARING_RESOURCE_OBJECT_URL' => (string)$Response['id'],
				'EDUSHARING_RESOURCE_TITLE' => $pageRef->getDBkey(),
				'EDUSHARING_RESOURCE_WIDTH' => (string)$Response['width'],
				'EDUSHARING_RESOURCE_HEIGHT' => (string)$Response['height'],
				'EDUSHARING_RESOURCE_FLOAT' => (string)$Response['float']
			];

			/*
			 * For new resources insert db record and set usage, mark as processed
			 */
			if ( $Response['action'] == 'new' ) {

				$usage = self::addResourceAndUsage( $eduService, $resourceData, $isRestore );
				// if we don't have a pageId (b/c page is new and not yet saved) we need to save the resourceIds
				// and add the pageId to the resource record in the PageSaveCompleteHook
				if ( $pageId === null ) {
					$resourceIds[] = $usage->resourceId;
				}
				$Response->addAttribute( 'resourceid', $usage->resourceId );
				$Response['action'] = 'processed';

			} elseif ( $Response['action'] == 'processed' ) {
					/*
					 * Try to get record for this resource with select conditions article id and resource id.
					 * If no record can be found this resource must be copied from another page.
					 * So add new record and add usage.
					 */

				$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
				$dbr = $dbProvider->getReplicaDatabase();

				$res = $dbr->select(
					'edusharing_resource',
					[
						'EDUSHARING_RESOURCE_ID',
						'EDUSHARING_RESOURCE_PAGE_ID',
						'EDUSHARING_RESOURCE_USAGE'
					],
					[
						'EDUSHARING_RESOURCE_PAGE_ID = ' . $pageId,
						'EDUSHARING_RESOURCE_ID = ' . $Response['resourceid']
					]
				);

				$resCount = 0;
				foreach ( $res as $r ) {
					$resCount++;
				}

				/*
				 * If record exists unset resource from deletion list
				 */
				if ( $resCount > 0 ) {

					$_resourceid = (int)$Response['resourceid'];
					unset( $old_list[$_resourceid] );

				} else {

					$usage = self::addResourceAndUsage( $eduService, $resourceData, $isRestore );

					$Response['resourceid'] = $usage->resourceId;
					// if we don't have a pageId (b/c page is new and not yet saved) we need to save the resourceIds
					// and add the pageId to the resource record in the PageSaveCompleteHook
					if ( $pageId === null ) {
						$resourceIds[] = $usage->resourceId;
					}
				}
			}

			/*
			* Write properties to text
			*/
			$_tag = html_entity_decode( str_replace( '<?xml version="1.0"?>', '', $Response->asXML() ) );
			// Replace both the original tag (as found in the text) and the normalized variant
			$text = str_replace( $edutagOriginal, $_tag, $text );
			if ( $edutagNormalized !== $edutagOriginal ) {
				$text = str_replace( $edutagNormalized, $_tag, $text );
			}

		}

		/*
		 * Delete resources that have been removed from article
		 */
		foreach ( $old_list as $item ) {
			/*
			 * Delete usage
			 */
			self::deleteResourceAndUsage( $eduService, $item );
		}

		return $resourceIds;
	}

	/**
	 * Adds/removes resources and usages when article is saved
	 *
	 * @param Parser $parser
	 * @param string &$text Text content (passed by reference)
	 * @return bool
	 */
	public function onParserPreSaveTransformComplete( $parser, &$text ) {
		$pageRef = $parser->getPage();

		// check if called from the "right" context, i.e. while saving a normal wikipage
		// to prevent exception when running in visual editor context
		if ( !$pageRef || $pageRef->getNamespace() === NS_SPECIAL ) {
			return true;
		}

		$user    = $parser->getUserIdentity();
		$services = MediaWikiServices::getInstance();
		$mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

		$eduService = new EduSharingService( $user, $mwConfig );
		if ( !$eduService->isAvailable ) {
			return true;
		}

		$resourceIds = self::syncArticleResources( $eduService, $pageRef, $text, false );

		// save resourceIds with missing pageId to be completed in PageSaveCompleteHook
		if ( $resourceIds ) {
			$pageKey = $pageRef->getNamespace() . ':' . $pageRef->getDBkey();
			if ( !isset( self::$pendingResourceIds[$pageKey] ) ) {
				self::$pendingResourceIds[$pageKey] = [];
			}
			self::$pendingResourceIds[$pageKey] = array_merge(
				self::$pendingResourceIds[$pageKey],
				$resourceIds
			);
		}
		return true;
	}

	/**
	 * Adds pageId reference to freshly created resources where missing
	 *
	 * @param \WikiPage $wikiPage
	 * @param \User $user
	 * @param string $summary
	 * @param int $flags
	 * @param \RevisionRecord $revisionRecord
	 * @param \MediaWiki\Storage\EditResult $editResult
	 * @return void
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		$pageId = $wikiPage->getId();
		$title  = $wikiPage->getTitle();
		$pageKey = $wikiPage->getNamespace() . ':' . $wikiPage->getDBkey();

		if ( empty( self::$pendingResourceIds[$pageKey] ) ) {
			return;
		}

		$resourceIds = self::$pendingResourceIds[$pageKey];

		$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbw = $dbProvider->getPrimaryDatabase();

		foreach ( $resourceIds as $resId ) {
			$dbw->update(
				'edusharing_resource',
				[
					'EDUSHARING_RESOURCE_PAGE_ID'   => $pageId,
					'EDUSHARING_RESOURCE_TITLE'     => $title->getPrefixedText(),
				],
				[
					'EDUSHARING_RESOURCE_ID'      => $resId,
					'EDUSHARING_RESOURCE_PAGE_ID' => null,
				],
				__METHOD__
			);
		}

		// Optional: aufräumen
		unset( self::$pendingResourceIds[$pageKey] );
	}

	/**
	 * Adds hook to parser that handles edu-sharing tags
	 *
	 * @param Parser $parser
	 * @return true
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'edusharing', [ self::class, 'wfEduSharingRender' ] );
		return true;
	}

	/**
	 * The callback function for converting the input text to HTML output
	 * Handles page view as well as page preview
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param \PPFrame $frame
	 * @return string
	 */
	public static function wfEduSharingRender( $input, array $args, Parser $parser, \PPFrame $frame ) {
		$isProcessed = isset( $args['action'] ) && $args['action'] === 'processed';
		$isPreview = $parser->getOptions()->getIsPreview();

		if ( !$isProcessed && !$isPreview ) {
			return 'Unknown edusharing action: "' . ( $args['action'] ?? '' ) . '"';
		}

		$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbr = $dbProvider->getReplicaDatabase();

		$res = $dbr->selectRow(
			'edusharing_resource',
			[ 'EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_USAGE', 'EDUSHARING_RESOURCE_OBJECT_URL' ],
			'EDUSHARING_RESOURCE_ID = ' . $args['resourceid'],
			__METHOD__,
			[ 'ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC' ]
		);

		if ( !$res ) {
			return 'No resource record found, please try and save the page again.';
		}

		$pageReference = $parser->getPage();
		if ( !$pageReference ) {
			return '';
		}

		$title = $pageReference instanceof Title ? $pageReference : Title::newFromPageReference( $pageReference );

		$user    = $parser->getUserIdentity();
		$services = MediaWikiServices::getInstance();
		$mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

		$eduService = new EduSharingService( $user, $mwConfig );

		if ( !$eduService->isAvailable ) {
			$float = $res->EDUSHARING_RESOURCE_FLOAT ?? ( $args['float'] ?? 'none' );
			switch ( $float ) {
				case 'left':
					$classes = 'tleft';
					break;
				case 'right':
					$classes = 'tright';
					break;
				case 'center':
					$classes = 'tnone center';
					break;
				case 'inline':
					$classes = 'tnone center';
					break;
				case 'none':
				default:
					$classes = 'tnone center';
					break;
			}
			$width = isset( $args['width'] ) ? (int)$args['width'] : null;
			$wrapperWidth = $width ? 'style="max-width: 100%; width: ' . $width . 'px;"' : '';
			$msgKey = 'edusharing-placeholder-unavailable';
			$msg = wfMessage( $msgKey )->isDisabled()
				? 'edu-sharing repository unavailable'
				: wfMessage( $msgKey )->text();
			if ( $eduService->availabilityError ) {
				$msg .= ' (' . $eduService->availabilityError . ')';
			}
			return '<div class="ext-edusharing-container ' . $classes . '" ' . $wrapperWidth .
				'><div class="thumbinner"><div class="edu_wrapper edusharing-render" ' .
				'style="padding:8px;border:1px dashed #ccc;">' . htmlspecialchars( $msg ) .
				'</div></div></div>';
		}

		$nodeId = str_replace( 'ccrep://local/', '', $args['id'] );
		$usage = new Usage(
			$nodeId,
			$args['nodeversion'] ?? null,
			(string)$title->getArticleID(),
			(string)$args['resourceid'],
			(string)$res->EDUSHARING_RESOURCE_USAGE
		);

		try {
			$securedNode = $eduService->getSecuredNodeByUsage( $usage );
		} catch ( \Throwable $e ) {
			$err = trim( $e->getMessage() );
			$float = $args['float'] ?? 'none';
			switch ( $float ) {
				case 'left':
					$classes = 'tleft';
					break;
				case 'right':
					$classes = 'tright';
					break;
				case 'center':
					$classes = 'tnone center';
					break;
				case 'inline':
					$classes = 'tnone center';
					break;
				case 'none':
				default:
					$classes = 'tnone center';
					break;
			}
			$width = isset( $args['width'] ) ? (int)$args['width'] : null;
			$wrapperWidth = $width ? 'style="max-width: 100%; width: ' . $width . 'px;"' : '';
			$hint = '';
			if ( stripos( $err, 'signature' ) !== false ) {
				$hintMsg = wfMessage( 'edusharing-signature-invalid-hint' )->isDisabled()
					? ''
					: wfMessage( 'edusharing-signature-invalid-hint' )->text();
				$hint = $hintMsg ?: '';
			}
			$msg = 'edu-sharing rendering failed';
			if ( $hint ) {
				$msg .= ': ' . $hint;
			} elseif ( $err ) {
				$msg .= ': ' . $err;
			}
			return '<div class="ext-edusharing-container ' . $classes . '" ' . $wrapperWidth .
				'><div class="thumbinner"><div class="edu_wrapper edusharing-render" ' .
				'style="padding:8px;border:1px dashed #ccc;">' . htmlspecialchars( $msg ) .
				'</div></div></div>';
		}

		$float = $args['float'] ?? 'none';
		switch ( $float ) {
			case 'left':
				$classes = 'tleft';
				break;
			case 'right':
				$classes = 'tright';
				break;
			case 'center':
				$classes = 'tnone center';
				break;
			case 'inline':
				$classes = 'tnone center';
				break;
			case 'none':
			default:
				$classes = 'tnone center';
				break;
		}

		$wrapperId = 'edusharing-render-' . $usage->resourceId . '-' . $usage->usageId;
		$width = isset( $args['width'] ) ? (int)$args['width'] : null;
		$wrapperWidth = $width ? 'style="max-width: 100%; width: ' . $width . 'px;"' : '';

		$proxyBase = rtrim( SpecialPage::getTitleFor( 'EduProxy' )->getFullURL(), '/' );
		$renderComponentBase = $proxyBase . '/web-components/rendering-service';
		$useServiceWorker = $eduService->config->enableServiceWorker;
		$resourceUrl = $proxyBase . '/public/redirect?mode=content'
			. '&nodeId=' . rawurlencode( $usage->nodeId )
			. '&nodeVersion=' . rawurlencode( $usage->nodeVersion ?? '' )
			. '&containerId=' . rawurlencode( $usage->containerId )
			. '&resourceId=' . rawurlencode( $usage->resourceId )
			. '&usageId=' . rawurlencode( $usage->usageId )
			. '&repoId=' . rawurlencode( $eduService->config->repoId );
		$previewUrl = $proxyBase . '/public/preview'
			. '?nodeId=' . rawurlencode( $usage->nodeId )
			. '&nodeVersion=' . rawurlencode( $usage->nodeVersion ?? '' )
			. '&containerId=' . rawurlencode( $usage->containerId )
			. '&resourceId=' . rawurlencode( $usage->resourceId )
			. '&usageId=' . rawurlencode( $usage->usageId )
			. '&repoId=' . rawurlencode( $eduService->config->repoId );

		$userData = [
			'authorityName' => $eduService->config->username,
		];

		$renderingBase = null;
		try {
			$renderingBase = rtrim( $eduService->getRenderingServiceUrl(), '/' );
		} catch ( \Throwable $e ) {
			$renderingBase = null;
		}
		// Rendering calls can go directly to rendering service; assets/service worker via proxy to avoid CORS
		$renderComponentProxyBase = $proxyBase . '/web-components/rendering-service';

		$componentData = [
			'id' => $wrapperId,
			'encodedNode' => $securedNode->securedNode,
			'signature' => $securedNode->signature,
			'jwt' => $securedNode->jwt,
			'renderUrl' => $renderingBase ?? $proxyBase,
			'encodedUser' => base64_encode( json_encode( $userData ) ),
			'assetsUrl' => $renderComponentProxyBase . '/assets',
			'scriptUrl' => $renderComponentProxyBase . '/main.js',
			'styleUrl' => $renderComponentProxyBase . '/styles.css',
			'serviceWorkerUrl' => $useServiceWorker ? $renderComponentProxyBase . '/edu-service-worker.js' : '',
			'previewUrl' => $previewUrl,
			'resourceUrl' => $resourceUrl,
			'apiUrl' => $proxyBase . '/rest',
			'width' => $width,
			'activateServiceWorker' => $useServiceWorker,
			'openInNewTab' => $eduService->config->openResourceInNewTab,
		];
		// Ensure client-side rendering script is loaded
		$parser->getOutput()->addModules( [ 'ext.eduSharing.render' ] );

		$componentJson = FormatJson::encode( $componentData, false, FormatJson::ALL_OK );

		$html = '<div class="ext-edusharing-container ' . $classes . '" ' . $wrapperWidth .
			'><div data-type="esObject" class="spinnerContainer"><div class="inner">' .
			'<div class="spinner1"></div></div><div class="inner"><div class="spinner2"></div>' .
			'</div><div class="inner"><div class="spinner3"></div></div></div>';
		$html .= '<div class="thumbinner"><div class="edu_wrapper edusharing-render" id="' .
			$wrapperId . '" data-edusharing-config="' . htmlspecialchars( $componentJson, ENT_QUOTES ) .
			'" ' . $wrapperWidth . '></div></div></div>';

		return $html;
	}

	/**
	 * Add module 'ext.eduSharing.display' providing js loadScript function
	 *
	 * @param \OutputPage $out
	 * @param \Skin $skin
	 *
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		global $wgOut;
		$wgOut->addModules( 'ext.eduSharing.display' );
		$wgOut->addModules( 'ext.eduSharing.visualEditor' );
	}

}
