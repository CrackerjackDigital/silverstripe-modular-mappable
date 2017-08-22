<?php
namespace Modular\Extensions\Model;

use Modular\Interfaces\Mappable as MappableInterface;
use Modular\ModelExtension;
use Modular\Traits\bitfield;
use Modular\Traits\mappable_mapper;
use Modular\Traits\mappable_model;

/**
 * Mappable base extension, concrete instances need to also provide mappable_map_dots type functionality
 *
 * @package Modular\Extensions\Model
 */
abstract class Mappable extends ModelExtension implements MappableInterface {
	use mappable_model;
	use mappable_mapper;
	use bitfield;

	private static $quaff_map = [
		# 'source' => [
	    #   'content.html' => 'Content'
	    # ]
	];

	public function model() {
		return $this->owner;
	}

	public function toMap() {
		return $this->model()->toMap();
	}
}