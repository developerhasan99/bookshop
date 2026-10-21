// Live product search for the header search box (endpoint in functions.php).
document.querySelectorAll( '.bookshop-search' ).forEach( ( form ) => {
	const input = form.querySelector( 'input[type="search"]' );
	const box = document.createElement( 'div' );
	box.className = 'bookshop-search__results';
	box.hidden = true;
	form.append( box );
	input.setAttribute( 'autocomplete', 'off' );

	let timer, ctrl;
	const decode = ( html ) => new DOMParser().parseFromString( html, 'text/html' ).body.textContent;
	const links = () => [ ...box.querySelectorAll( 'a' ) ];

	const render = ( items ) => {
		box.innerHTML = '';
		if ( ! items.length ) {
			box.innerHTML = '<p class="bookshop-search__empty">কোনো বই পাওয়া যায়নি</p>';
		}
		items.forEach( ( p ) => {
			const a = document.createElement( 'a' );
			a.href = p.permalink;
			a.innerHTML = '<img alt="" loading="lazy"><span><strong></strong><span class="price"></span></span>';
			a.querySelector( 'img' ).src = p.image;
			a.querySelector( 'strong' ).textContent = decode( p.name );
			a.querySelector( '.price' ).innerHTML = p.price_html; // WooCommerce-generated markup.
			box.append( a );
		} );
		if ( items.length ) {
			const all = document.createElement( 'a' );
			all.className = 'bookshop-search__all';
			all.href = form.action + '?' + new URLSearchParams( new FormData( form ) );
			all.textContent = 'সব ফলাফল দেখুন →';
			box.append( all );
		}
		box.hidden = false;
	};

	input.addEventListener( 'input', () => {
		clearTimeout( timer );
		const q = input.value.trim();
		if ( q.length < 2 ) {
			box.hidden = true;
			return;
		}
		timer = setTimeout( async () => {
			ctrl?.abort();
			ctrl = new AbortController();
			try {
				const url = bookshopSearch.api + '?search=' + encodeURIComponent( q );
				render( await ( await fetch( url, { signal: ctrl.signal } ) ).json() );
			} catch ( e ) {
				if ( e.name !== 'AbortError' ) {
					box.hidden = true;
				}
			}
		}, 250 );
	} );

	input.addEventListener( 'focus', () => {
		box.hidden = ! box.childElementCount || input.value.trim().length < 2;
	} );

	// Arrow keys move through results, Escape closes.
	form.addEventListener( 'keydown', ( e ) => {
		const list = links();
		const i = list.indexOf( document.activeElement );
		if ( e.key === 'Escape' ) {
			box.hidden = true;
			input.focus();
		} else if ( e.key === 'ArrowDown' && list.length && ! box.hidden ) {
			e.preventDefault();
			list[ Math.min( i + 1, list.length - 1 ) ].focus();
		} else if ( e.key === 'ArrowUp' && i > -1 ) {
			e.preventDefault();
			( i === 0 ? input : list[ i - 1 ] ).focus();
		}
	} );

	document.addEventListener( 'click', ( e ) => {
		if ( ! form.contains( e.target ) ) {
			box.hidden = true;
		}
	} );
} );
