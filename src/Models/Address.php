<?php namespace Lecturize\Addresses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webpatser\Countries\Countries;

/**
 * Class Address
 * @package Lecturize\Addresses\Models
 */
class Address extends Model
{
	// use SoftDeletes;

	/**
     * @todo make this editable via config file
     * @inheritdoc
	 */
	protected $table = 'addresses';

	/**
     * @inheritdoc
	 */
	protected $fillable = [
		'line1',
		'line2',
		'street',
		'city',
		'state',
		'post_code',
		'country_id',
		'lat',
		'lng',
		'addressable_id',
		'addressable_type',
		'is_primary',
		'is_billing',
		'is_shipping',
	];

	/**
     * @inheritdoc
	 */
	protected $hidden = [];

	/**
     * @inheritdoc
	 */
	protected $dates = ['deleted_at'];

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function country()
	{
		return $this->belongsTo(Countries::class, 'country_id');
	}

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\MorphTo
	 */
	public function addressable()
	{
		return $this->morphTo();
	}

	/**
	 * {@inheritdoc}
	 */
	public static function boot() {
		parent::boot();

		static::saving(function($address) {
			if ( config('lecturize.addresses.geocode', true) ) {
				$address->geocode();
			}
		});
	}

	/**
	 * Get validation rules
	 *
	 * @return array
	 */
	public static function getValidationRules() {
		$rules = [
			'line1'     => 'required|string|min:3|max:60',
			'line2'     => 'required|string|min:3|max:60',
			'street'     => 'required|string|min:3|max:60',
			'city'       => 'required|string|min:3|max:60',
			'state'      => 'string|min:3|max:60',
			'post_code'  => 'required|min:4|max:10|AlphaDash',
			'country_id' => 'required|integer',
		];

		foreach( config('lecturize.addresses.flags', ['public', 'primary', 'billing', 'shipping']) as $flag ) {
			$rules['is_'.$flag] = 'boolean';
		}

		return $rules;
	}

	/**
	 * Try to fetch the coordinates from Google
	 * and store it to database
	 *
	 * @return $this
	 */
	public function geocode() {
		// build query string
		$query = [];
		$query[] = $this->street       ?: '';
		$query[] = $this->city         ?: '';
		$query[] = $this->state        ?: '';
		$query[] = $this->post_code    ?: '';
		$query[] = $this->getCountry() ?: '';

		// build query string
		$query = trim( implode(',', array_filter($query)) );
		$query = str_replace(' ', '+', $query);

		// build url
		$url = 'https://maps.google.com/maps/api/geocode/json?address='.$query.'&sensor=false';

		// try to get geo codes
		if ( $geocode = file_get_contents($url) ) {
			$output = json_decode($geocode);

			if ( count($output->results) && isset($output->results[0]) ) {
				if ( $geo = $output->results[0]->geometry ) {
					$this->lat = $geo->location->lat;
					$this->lng = $geo->location->lng;
				}
			}
		}

		return $this;
	}

	/**
	 * Get address as array
	 *
	 * @return array|null
	 */
	public function getArray() {
		$address = $two = [];

		$two[] = $this->post_code ?: '';
		$two[] = $this->city      ?: '';
		$two[] = $this->state     ? '('. $this->state .')' : '';

		$address[] = $this->line1 ?: '';
		$address[] = $this->line2 ?: '';
		$address[] = $this->street ?: '';
		$address[] = implode( ' ', array_filter($two) );
		$address[] = $this->getCountry() ?: '';

		if ( count($address = array_filter($address)) > 0 )
			return $address;

		return null;
	}

	/**
	 * Get address as html block
	 *
	 * @return string|null
	 */
	public function getHtml() {
		if ( $address = $this->getArray() )
			return '<address>'. implode( '<br />', array_filter($address) ) .'</address>';

		return null;
	}

	/**
	 * Get address as a simple line
	 *
	 * @return string|null
	 */
	public function getLine() {
		if ( $address = $this->getArray() )
			return implode( ', ', array_filter($address) );

		return null;
	}

	/**
	 * Try to get country name
	 *
	 * @return string|null
	 */
	public function getCountry() {
		if ( $this->country && $country = $this->country->name )
			return $country;

		return null;
	}
}