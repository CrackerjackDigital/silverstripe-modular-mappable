<?php

namespace Modular\Traits;

use Modular\Exceptions\Mappable as Exception;
use Modular\Interfaces\Mappable as MappableInterface;

trait mappable_model {
	/**
	 * @return \DataObject|MappableInterface
	 */
	abstract public function model();

	/**
	 * Return the delimiter in source path, e.g. '.'
	 * @return mixed
	 */
	abstract public function mappableSourcePathDelimiter();

	/**
	 * @param string $sourceName names of the source to lookup in config.mappable_map of the model
	 * @param int    $options
	 *
	 * @return array
	 * @throws Exception
	 */
	public function mappableMapForSource( $sourceName, $options = MappableInterface::MapDeep ) {
		$model = $this->model();

		$maps = $model->config()->get( 'mappable_map' ) ?: [];

		if ( ! isset( $maps[ $sourceName ] ) ) {
			throw new Exception( "No map for endpoint '$sourceName'" );
		}
		$delimiter = $this->mappableSourcePathDelimiter();

		foreach ( $maps[ $sourceName ] as $dataPath => $modelPath ) {
			$fieldInfo            = self::decode_map( $dataPath, $modelPath, $delimiter);
			$newMap[ $modelPath ] = $fieldInfo;
		}

		$model->extend( 'mappableUpdateSourceMap', $newMap, $endpoint, $options );

		return $newMap;
	}

	/**
	 * Given local and remote paths for mapping decompose into an array usefull during the mapping process.
	 *
	 * @param string $dataPath  in incoming data, e.g. a dot path on the left of a quaff_map configuration map
	 * @param string $modelPath in SilverStripe e.g a field name on the right of a quaff_map
	 *
	 * @param string $delimiter
	 *
	 * @return array see comments on return array
	 */
	public static function decode_map( $dataPath, $modelPath, $delimiter = '.' ) {
		$foreignKey = $tagField = $method = $relationship = null;

		if ( false !== strpos( $modelPath, '.' ) ) {
			// model path is a relationship which should be resolved by the mapper
			$relationship = $modelPath;
			list( $modelPath ) = explode( $delimiter, $modelPath );
		}

		if ( '=' == substr( $dataPath, 0, 1 ) ) {
			// remote path is a lookup to find the item in the database
			$foreignKey = $dataPath = substr( $dataPath, 1 );
		}
		if ( '[]' == substr( $dataPath, - 2, 2 ) ) {
			// remote path is a set of tags which should be concatenated to the local path
			$dataPath = substr( $dataPath, 0, - 2 );
			$tagField = $modelPath;
		}
		if ( '()' == substr( $dataPath, - 2, 2 ) ) {
			// remote path is a method invocation which should be called with the value by the mapper
			// this may result in a call e.g. to a QuaffMapHelper extension on the model being mapped.
			list( $dataPath, $method ) = explode( $delimiter, substr( $dataPath, 0, - 2 ) );
			// keep method in name to prevent array key collision across same source going to different fields
			$dataPath .= ".$method";
		}

		return [
			$dataPath,          // processed path in the api data
			$modelPath,         // processed path in the model (a field name)
			$foreignKey,        // set if search field used to match existing models
			$tagField,          // set of tags to concatenate
			$method,            // method to call for this field
			$relationship,      // relationship to use for this field
		];
	}

	/**
	 * Set fields on the extended model from the values, optionally prepending and appending prefix and suffix respectively to the field name being set.
	 *
	 * @param array  $values
	 * @param string $prefix
	 * @param string $suffix
	 */
	public function mappableMapValuesToFields( array $values, $prefix = '', $suffix = '' ) {
		$model = $this->model();

		foreach ( $values as $name => $value ) {
			$fieldName         = $prefix . $name . $suffix;
			$model->$fieldName = $value;
		}
	}
}