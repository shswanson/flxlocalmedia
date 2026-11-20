<?php
/**
 * Template Name: Solutions Overview
 *
 * Everything we sell, in the four groups of the primary menu: Stations, Media,
 * Advertising and Digital. Names, links, stats and logos all come from
 * flxlm_nav_groups(), flxlm_get_product() and the station registry; nothing
 * about a product is written here.
 *
 * @package flxlm
 */

$groups = array();
foreach ( flxlm_nav_groups() as $g ) {
	$groups[ $g['key'] ] = $g;
}
$stations = flxlm_hub_stations();

/**
 * The cards of one menu group: each menu item that is a product page.
 *
 * @param array $group Group from flxlm_nav_groups().
 * @return array[] Each: item (menu entry), p (product), url.
 */
$sol_cards = function ( $group ) {
	$cards = array();
	if ( empty( $group['items'] ) ) {
		return $cards;
	}
	foreach ( $group['items'] as $item ) {
		$url  = flxlm_nav_url( $item );
		$slug = basename( untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		$p    = flxlm_get_product( $slug );
		if ( $p ) {
			$cards[] = array( 'item' => $item, 'p' => $p, 'url' => $url );
		}
	}
	return $cards;
};

/**
 * The picture at the top of a card.
 *
 * @param string $kind media, advertising or digital.
 * @param array  $p    Product from flxlm_get_product().
 * @param array  $item Menu item.
 */
$sol_visual = function ( $kind, $p, $item ) {
	$logo_url = function ( $file ) {
		return flxlm_asset_url( 'assets/images/products/logos/' . $file );
	};
	if ( 'media' === $kind ) {
		if ( $p['hero_mark'] ) {
			echo '<span class="sol-mark sol-mark--' . esc_attr( $p['hero_mark_style'] ) . '"><img src="' . esc_url( $p['hero_mark'] ) . '" alt="' . esc_attr( $p['name'] . ' logo' ) . '" loading="lazy" decoding="async"></span>';
		} elseif ( $p['logo'] ) {
			echo '<img class="sol-logo" src="' . esc_url( $p['logo'] ) . '" alt="' . esc_attr( $p['name'] . ' logo' ) . '" loading="lazy" decoding="async">';
		} else {
			echo '<span class="sol-wordmark">' . esc_html( $p['name'] ) . '</span>';
		}
		return;
	}

	if ( 'advertising' === $kind ) {
		// Plain marks (the ones with a size) are the real team and racing logos.
		$marks = array();
		foreach ( $p['hero_logos'] as $lg ) {
			if ( $lg['w'] > 0 && $lg['h'] > 0 ) {
				$marks[] = $lg;
			}
		}
		if ( $marks ) {
			echo '<span class="sol-chips sol-chips--panel">';
			foreach ( array_slice( $marks, 0, 3 ) as $lg ) {
				$h = min( 52, max( 24, round( $lg['h'] * 1.15 ) ) );
				$w = round( $h * $lg['w'] / $lg['h'] );
				echo '<img src="' . esc_url( $logo_url( $lg['file'] ) ) . '" alt="' . esc_attr( $lg['alt'] ) . '" width="' . (int) $w . '" height="' . (int) $h . '" loading="lazy" decoding="async">';
			}
			echo '</span>';
			return;
		}
		$t = $p['testimonial'];
		if ( $t && $t['thumb'] ) {
			echo '<img class="sol-photo" src="' . esc_url( $t['thumb'] ) . '" alt="" loading="lazy" decoding="async">';
			$who = trim( $t['person'] . ( '' !== $t['person'] && '' !== $t['business'] ? ', ' : '' ) . $t['business'] );
			if ( '' !== $who ) {
				echo '<span class="sol-photo__cap">' . esc_html( $who ) . '</span>';
			}
			return;
		}
	}

	// Digital: the recognizable marks already on the product's offers.
	$files = array();
	foreach ( $p['offers'] as $o ) {
		foreach ( $o['logos'] as $lg ) {
			if ( isset( $lg['file'] ) && 0 === strpos( $lg['file'], 'ext-' ) && ! isset( $files[ $lg['file'] ] ) ) {
				$files[ $lg['file'] ] = $lg['alt'];
			}
		}
	}
	if ( $files ) {
		echo '<span class="sol-chips">';
		foreach ( array_slice( $files, 0, 4, true ) as $file => $alt ) {
			echo '<span class="sol-chip"><img src="' . esc_url( $logo_url( $file ) ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async"></span>';
		}
		echo '</span>';
		return;
	}
	// No outside marks (content marketing runs on our own news site): show that site's mark.
	$fl = flxlm_get_product( 'fldn' );
	if ( $fl && $fl['hero_mark'] ) {
		echo '<span class="sol-mark sol-mark--panel"><img src="' . esc_url( $fl['hero_mark'] ) . '" alt="' . esc_attr( $fl['name'] . ' logo' ) . '" loading="lazy" decoding="async"></span>';
	} else {
		echo '<span class="sol-wordmark">' . esc_html( $p['name'] ) . '</span>';
	}
};

/**
 * One card.
 *
 * @param string $kind media, advertising or digital.
 * @param array  $c    Entry from $sol_cards().
 */
$sol_card = function ( $kind, $c ) use ( $sol_visual ) {
	$p    = $c['p'];
	$stat = ! empty( $p['stats'][0] ) ? $p['stats'][0] : null;
	$name = $c['item']['label'];
	$blur = isset( $c['item']['blurb'] ) ? $c['item']['blurb'] : '';
	?>
	<a class="sol-card sol-card--<?php echo esc_attr( $kind ); ?>" href="<?php echo esc_url( $c['url'] ); ?>"<?php echo 'media' === $kind ? ' style="' . esc_attr( $p['css_vars'] ) . '"' : ''; ?>>
		<span class="sol-card__visual"><?php $sol_visual( $kind, $p, $c['item'] ); ?></span>
		<span class="sol-card__body">
			<h3 class="sol-card__name"><?php echo esc_html( $name ); ?></h3>
			<?php if ( '' !== $blur ) : ?>
				<span class="sol-card__blurb"><?php echo esc_html( $blur ); ?></span>
			<?php endif; ?>
			<?php if ( $stat ) : ?>
				<span class="sol-card__stat"><strong><?php echo esc_html( $stat['value'] ); ?></strong> <span><?php echo esc_html( $stat['label'] ); ?></span></span>
			<?php endif; ?>
			<span class="sol-card__go"><?php echo esc_html( 'Explore ' . $name ); ?> <span aria-hidden="true">&rarr;</span></span>
		</span>
	</a>
	<?php
};

flxlm_pg_begin( array(
	'accent'  => 'navy',
	'eyebrow' => 'Advertising',
	'title'   => 'Marketing Solutions',
	'lead'    => 'Everything we sell, in four groups: our stations, our media, advertising packages and digital services.',
) );
get_header();
flxlm_pg_open();

$sections = array(
	'media'       => array( 'eyebrow' => 'Our own brands', 'title' => 'Media', 'text' => 'News, a daily email, a magazine, local awards and an app, each with its own audience.', 'class' => 'section--gray' ),
	'advertising' => array( 'eyebrow' => 'Ways to advertise', 'title' => 'Advertising', 'text' => 'Sports, shop-local campaigns, giveaways, seasonal packages and live events.', 'class' => '' ),
	'digital'     => array( 'eyebrow' => 'Online', 'title' => 'Digital', 'text' => 'Websites, listings, ads and stories that bring customers to you.', 'class' => 'section--gray' ),
);
?>

<?php if ( $stations ) : ?>
<section class="section sol-sec" aria-labelledby="sol-stations">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="section__eyebrow">On the air</span>
			<h2 class="section__title" id="sol-stations"><?php echo esc_html( isset( $groups['stations']['label'] ) ? $groups['stations']['label'] : 'Stations' ); ?></h2>
			<p class="sol-head__text">Seven stations across the Finger Lakes. Pick one, or let us build a schedule across several.</p>
		</div>
		<div class="flxlm-hub sol-hub">
			<div class="hb-strip">
				<?php foreach ( $stations as $t ) : ?>
					<?php flxlm_hub_station_tile( $t, true, false ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php if ( isset( $groups['stations']['url'] ) ) : ?>
			<p class="sol-more"><a href="<?php echo esc_url( flxlm_nav_url( $groups['stations']['url'] ) ); ?>"><?php echo esc_html( isset( $groups['stations']['all_label'] ) ? $groups['stations']['all_label'] : 'All stations' ); ?> <span aria-hidden="true">&rarr;</span></a></p>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php
foreach ( $sections as $key => $sec ) :
	if ( empty( $groups[ $key ] ) ) {
		continue;
	}
	$cards = $sol_cards( $groups[ $key ] );
	if ( ! $cards ) {
		continue;
	}
	?>
<section class="section sol-sec <?php echo esc_attr( $sec['class'] ); ?>" aria-labelledby="sol-<?php echo esc_attr( $key ); ?>">
	<div class="sol-wrap">
		<div class="sol-head">
			<span class="section__eyebrow"><?php echo esc_html( $sec['eyebrow'] ); ?></span>
			<h2 class="section__title" id="sol-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $groups[ $key ]['label'] ); ?></h2>
			<p class="sol-head__text"><?php echo esc_html( $sec['text'] ); ?></p>
		</div>
		<div class="sol-grid sol-grid--<?php echo (int) count( $cards ); ?>">
			<?php foreach ( $cards as $c ) : ?>
				<?php $sol_card( $key, $c ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endforeach; ?>

<!-- Testimonial section -->
<?php
$testimonials = flxlm_get_testimonials( array( 'posts_per_page' => 3 ) );
if ( $testimonials->have_posts() ) :
?>
<section class="section">
	<div class="container">
		<div class="section__header">
			<h2 class="section__title">Our Clients' Results</h2>
		</div>
		<div class="testimonial-grid">
			<?php while ( $testimonials->have_posts() ) : $testimonials->the_post(); ?>
				<?php get_template_part( 'template-parts/testimonial-card' ); ?>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php
flxlm_pg_close();
get_footer();
