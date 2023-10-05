/**
 * Tracking of Gtag events.
 *
 * @typedef { import( '@playwright/test' ).Request } Request
 * @typedef { import( '@playwright/test' ).Page } Page
 */

/**
 * Tracks when the Gtag Event request matching a specific name is sent.
 *
 * @param {Page} page
 * @param {string} eventName Event name to match.
 * @param {string|null} URLpath The URL path where the event should be triggered.
 * @return {Promise<Request>} Matching request.
 */
export function trackGtagEvent( page, eventName, URLpath = null ) {
	const eventPath = '/pagead/';
	return page.waitForRequest( ( request ) => {
		const url = request.url();
		const match = encodeURIComponent( 'event=' + eventName );
		const origin = new URL( page.url() ).origin;

		return (
			url.includes( eventPath ) &&
			url.includes( match ) &&
			( URLpath
				? url.includes(
						`url=${ encodeURIComponent(
							`${ origin }/${ URLpath }`
						) }`
				  )
				: true )
		);
	} );
}

/**
 * Retrieve data from a Gtag event.
 *
 * @param {Request} request
 * @return {Object} Data sent with the event.
 */
export function getEventData( request ) {
	const url = new URL( request.url() );
	const params = new URLSearchParams( url.search );
	const data = Object.fromEntries(
		params
			.get( 'data' )
			.split( ';' )
			.map( ( pair ) => pair.split( '=' ) )
	);

	if ( params.get( 'value' ) ) {
		data.value = params.get( 'value' );
	}

	if ( params.get( 'currency_code' ) ) {
		data.currency_code = params.get( 'currency_code' );
	}

	return data;
}
