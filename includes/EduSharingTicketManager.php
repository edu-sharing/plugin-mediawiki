<?php

namespace MediaWiki\Extension\EduSharing;

use MediaWiki\MediaWikiServices;
use EduSharingApiClient\EduSharingAuthHelper;

class EduSharingTicketManager {

    private $cache;
    private EduSharingAuthHelper $authHelper;
    private EduSharingConfig $config;

    public function __construct( EduSharingAuthHelper $authHelper, EduSharingConfig $config ) {
        $this->cache      = MediaWikiServices::getInstance()->getMainObjectStash();
        $this->authHelper = $authHelper;
        $this->config     = $config;
    }

    /**
     * Liefert ein Ticket für den *aktuellen* (Repo-)User,
     * userspezifisch über die MW-Session gecached.
     */
    public function getTicket(): ?string {
        $key = $this->cache->makeKey( 'edusharing-ticket', $this->config->username );

        $ticket = $this->cache->get( $key );
        if ( $ticket !== null && $this->isTicketValid( $ticket ) ) {
            return $ticket;
        }

        $ticket = $this->fetchTicketFromRepo( $this->config->username );
        if ( $ticket !== null ) {
            // TTL nach Ticket-Laufzeit wählen, z.B. 1800 Sekunden
            $this->cache->set( $key, $ticket, 1800 );
        }

        return $ticket;
    }

    private function isTicketValid( string $ticket ): bool {
        try {
            $ticketInfo = $this->authHelper->getTicketAuthenticationInfo( $ticket );
        } catch ( \Exception $e ) {
            return false;
        }

        return isset( $ticketInfo['statusCode'] )
            && $ticketInfo['statusCode'] === 'OK';
    }

    private function fetchTicketFromRepo( string $username ): ?string {
        try {
            return $this->authHelper->getTicketForUser( $username );
        } catch ( \Exception $e ) {
            error_log( "Couldn't get ticket from EduSharing repository ($e)" );
            return null;
        }
    }
}
