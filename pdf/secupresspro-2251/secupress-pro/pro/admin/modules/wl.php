<?php
defined( 'ABSPATH' ) or die( 'Something went wrong.' );


$this->set_current_section( 'secupress_display_white_label' );
$this->set_section_description( __( 'You can change the name of the plugin, this will be shown on the plugins page, only when activated.', 'secupress' ) );
$this->add_section( __( 'White Label', 'secupress' ) );


$this->add_field( array(
	'title'        => __( 'Plugin name', 'secupress' ),
	'label_for'    => $this->get_field_name( 'plugin_name' ),
	'type'         => 'text',
) );

$this->add_field( array(
	'title'        => __( 'Plugin URL', 'secupress' ),
	'label_for'    => $this->get_field_name( 'plugin_URI' ),
	'type'         => 'url',
) );

$this->add_field( array(
	'title'        => __( 'Plugin description', 'secupress' ),
	'label_for'    => $this->get_field_name( 'description' ),
	'type'         => 'textarea',
) );

$this->add_field( array(
	'title'        => __( 'Plugin author', 'secupress' ),
	'label_for'    => $this->get_field_name( 'author' ),
	'type'         => 'text',
) );

$this->add_field( array(
	'title'        => __( 'Plugin author URL', 'secupress' ),
	'label_for'    => $this->get_field_name( 'author_URI' ),
	'type'         => 'url',
) );
