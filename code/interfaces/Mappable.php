<?php

namespace Modular\Interfaces;

/**
 * Interface to add to DataObjects which support Mapping. This is not declared directly on models as it is
 * implemented by an Extension , however is useful to use as a return type or parameter type hint.
 */
interface Mappable {
	const DecodeNone = 8;       // don't decode values
	const DecodeJSON = 16;      // decode from json
	const DecodeURL  = 32;        // decode using urldecode

	const MapDeep          = 64;
	const MapOwnFieldsOnly = 128;

	const OptionSkipNulls                   = 256;          // update missing api values to null
	const OptionShallow                     = 512;            // don't import relationships if set
	const OptionSkipRelationships           = 1024;      // don't import/decode tag fields
	const OptionRemoveObsoleteRelationships = 2048;    // remove relationships
	const OptionClearOneToMany              = 4096;
	const OptionDeleteOneToMany             = 8192;  // delete implies clear so 32 | 16
	const OptionCreateRelatedModels         = 16384;

	const DefaultMappableOptions = self::DecodeNone | self::MapDeep | self::OptionDeleteOneToMany | self::OptionCreateRelatedModels;

	const DefaultMapMethodPrefix = 'mappable';

	const DefaultPathDelimiter = '.';

	const DefaultTagDelimiter = '|';

	/**
	 * From DataObject but we use it so declare it
	 *
	 * @return array
	 */
	public function toMap();

	/**
	 * Import data to the model for the source.
	 *
	 * @param string $sourceName such as 'get/online-activities' or 'solr'
	 * @param array  $data       to be imported via the map found for the source
	 * @param int    $options
	 *
	 * @return mixed
	 */
	public function mappableUpdate( $sourceName, $data, $options = self::DefaultMappableOptions );

	/**
	 * Returns the map for a given source for the extended model.
	 *
	 * @param string $sourceName such as 'get/online-activities' or 'solr'
	 * @param int    $options
	 *
	 * @return array
	 */
	public function mappableMapForSource( $sourceName, $options = self::MapDeep );

}