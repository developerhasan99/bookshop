// Loading state for product-card add-to-cart buttons: spinner + disabled until WooCommerce's
// cart request finishes. The label updates optimistically, so we watch the request instead.

let started = 0;
let pending = 0;
const idle = new Set();

// Count in-flight Store API cart writes (adds go to /wc/store/v1/batch).
const nativeFetch = window.fetch;
window.fetch = function ( input, init ) {
	const url = decodeURIComponent( String( input?.url || input ) );
	const method = ( init?.method || input?.method || 'GET' ).toUpperCase();
	if ( 'GET' === method || ! /\/wc\/store\/v1\/(batch|cart)/.test( url ) ) {
		return nativeFetch.apply( this, arguments );
	}
	started++;
	pending++;
	return nativeFetch.apply( this, arguments ).finally( () => {
		pending--;
		idle.forEach( ( fn ) => fn() );
	} );
};

// Capture phase: runs before WooCommerce's own click handler, so a second click while
// loading never reaches it (disabled is only set after the first click has been handled).
document.addEventListener( 'click', ( e ) => {
	const btn = e.target.closest( '.wc-block-product button.wc-block-components-product-button__button' );
	if ( ! btn ) {
		return;
	}
	if ( btn.classList.contains( 'is-loading' ) ) {
		e.preventDefault();
		e.stopImmediatePropagation();
		return;
	}
	const startedBefore = started;

	btn.classList.add( 'is-loading' );
	btn.setAttribute( 'aria-busy', 'true' );
	setTimeout( () => ( btn.disabled = true ) ); // After WooCommerce's own click handler has run.

	const done = () => {
		idle.delete( check );
		clearTimeout( timer );
		btn.classList.remove( 'is-loading' );
		btn.removeAttribute( 'aria-busy' );
		btn.disabled = false;
	};
	// Done once a request has started since the click and none are still running.
	const check = () => started > startedBefore && 0 === pending && done();
	idle.add( check );
	const timer = setTimeout( done, 10000 ); // Safety net if no request is ever sent.
}, true );
