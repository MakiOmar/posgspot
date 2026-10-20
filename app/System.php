<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Property values read during the current HTTP request. ModuleUtil::isModuleInstalled()
     * looks up "{module}_version" once per module on every menu/permission check.
     * PHP-FPM resets statics between requests; CLI processes skip the memo.
     *
     * @var array<string, mixed>
     */
    private static array $propertyMemo = [];

    protected static function booted(): void
    {
        static::saved(fn () => self::flushPropertyMemo());
        static::deleted(fn () => self::flushPropertyMemo());
    }

    /** Forget memoized property values after a write that bypasses the helpers below. */
    public static function flushPropertyMemo(): void
    {
        self::$propertyMemo = [];
    }

    /**
     * Return the value of the property
     *
     * @param $key string
     * @return mixed
     */
    public static function getProperty($key)
    {
        if (array_key_exists($key, self::$propertyMemo)) {
            return self::$propertyMemo[$key];
        }

        $row = System::where('key', $key)
                ->first();

        $value = isset($row->value) ? $row->value : null;

        if (! app()->runningInConsole()) {
            self::$propertyMemo[$key] = $value;
        }

        return $value;
    }

    /**
     * Return the value of the multiple properties
     *
     * @param $keys array
     * @return array
     */
    public static function getProperties($keys, $pluck = false)
    {
        if ($pluck == true) {
            return System::whereIn('key', $keys)
                ->pluck('value', 'key');
        } else {
            return System::whereIn('key', $keys)
                ->get()
                ->toArray();
        }
    }

    /**
     * Return the system default currency details
     *
     * @param void
     * @return object
     */
    public static function getCurrency()
    {
        $c_id = System::where('key', 'app_currency_id')
                ->first()
                ->value;

        $currency = Currency::find($c_id);

        return $currency;
    }

    /**
     * Set the property
     *
     * @param $key
     * @param $value
     * @return void
     */
    public static function setProperty($key, $value)
    {
        System::where('key', $key)
            ->update(['value' => $value]);
        self::flushPropertyMemo();
    }

    /**
     * Remove the specified property
     *
     * @param $key
     * @return void
     */
    public static function removeProperty($key)
    {
        System::where('key', $key)
            ->delete();
        self::flushPropertyMemo();
    }

    /**
     * Add a new property, if exist update the value
     *
     * @param $key
     * @param $value
     * @return void
     */
    public static function addProperty($key, $value)
    {
        System::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
