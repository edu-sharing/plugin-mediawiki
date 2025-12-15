/**
 * EduSharing Loading Spinner
 *
 * This script provides functions to manage loading spinners for EduSharing containers.
 * It removes spinners once the EduSharing content is rendered and observes dynamic changes
 * in the DOM to ensure spinners are removed as needed.
 *
 * @author   Jan Böhme <jan@idea-sketch.com>
 * @author   Uwe Schützenmeister <uwe@idea-sketch.com>
 * @license  MIT
 */

/**
 * Removes the loading spinner from a specified container.
 *
 * @method removeSpinner
 * @param {HTMLElement} container The container element from which the spinner should be removed
 */
function removeSpinner( container ) {
	const spinner = container.querySelector( '.spinnerContainer' );
	if ( spinner ) {
		spinner.remove();
	}
}

/**
 * Checks all EduSharing containers and removes spinners if the content is rendered.
 *
 * @method checkContainers
 */
function checkContainers() {
	const containers = document.querySelectorAll( '.mw-edusharing-container' );
	containers.forEach( ( container ) => {
		if ( container.querySelector( 'edu-sharing-render' ) ) {
			removeSpinner( container );
		}
	} );
}

// MutationObserver to watch for dynamic changes in the DOM
const observer = new MutationObserver( ( mutations ) => {
	mutations.forEach( ( mutation ) => {
		if ( mutation.addedNodes ) {
			checkContainers();
		}
	} );
} );

// Observe the entire document body for changes
observer.observe( document.body, {
	childList: true,
	subtree: true
} );

/**
 * Initializes the EduSharing spinner management when the document is ready.
 */
$( ( $ ) => {
	if ( typeof eduSharingScripts === 'function' ) {
		eduSharingScripts();
	}
	checkContainers();
} );

/**
 * Reloads the EduSharing scripts when the VisualEditor is deactivated.
 */
if ( typeof mw !== 'undefined' && typeof mw.hook === 'function' ) {
	mw.hook( 've.deactivationComplete' ).add( () => {
		if ( typeof eduSharingScripts === 'function' ) {
			eduSharingScripts();
		}
	} );
}
