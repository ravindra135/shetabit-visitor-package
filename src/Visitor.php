<?php

namespace Shetabit\Visitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PulkitJalan\GeoIP\GeoIP;
use PulkitJalan\GeoIP\Exceptions\GeoIPException;
use Shetabit\Visitor\Contracts\UserAgentParser;
use Shetabit\Visitor\Exceptions\DriverNotFoundException;
use Shetabit\Visitor\Models\Visit;

class Visitor implements UserAgentParser
{
    /**
     * except.
     *
     * @var array
     */
    protected $except;
    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Driver name.
     *
     * @var string
     */
    protected $driver;

    /**
     * Driver instance.
     *
     * @var object
     */
    protected $driverInstance;

    /**
     * Request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * Visitor (user) instance.
     *
     * @var Model|null
     */
    protected $visitor;

    /**
     * Creating GeoIP Instance
     * 
     * @var GeoIP
     */
    protected $geoip;

    /**
     * Visitor constructor.
     *
     * @param $config
     *
     * @throws \Exception
     */
    public function __construct(Request $request, $config)
    {
        $this->request = $request;
        $this->config = $config;
        $this->except = $config['except'];
        $this->via($this->config['default']);
        $this->setVisitor($request->user());

        // Prevents GeoIP code breaks, if GEOIP Request Limit Exhausted;
        try {
            $this->geoip = new GeoIP();
        } catch (GeoIPException $e) {
            $this->geoip = null;
            // Logs Error, When GEOIP is fails;
            Log::error($e->getMessage());
        }
    }

    /**
     * Change the driver on the fly.
     *
     * @param $driver
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function via($driver)
    {
        $this->driver = $driver;
        $this->validateDriver();

        return $this;
    }

    /**
     * Retrieve request's data
     *
     * @return array
     */
    public function request() : array
    {
        return $this->request->all();
    }

    /**
     * Retrieve user's ip.
     *
     * @return string|null
     */
    public  function ip() : ?string
    {
        return $this->request->ip();
    }

    /**
     * Retrieve request's url
     *
     * @return string
     */
    public function url() : string
    {
        return $this->request->fullUrl();
    }

    /**
     * Retrieve request's referer
     *
     * @return string|null
     */
    public function referer() : ?string
    {
        return $_SERVER['HTTP_REFERER'] ?? null;
    }

    /**
     * Retrieve request's method.
     *
     * @return string
     */
    public function method() : string
    {
        return $this->request->getMethod();
    }

    /**
     * Retrieve http headers.
     *
     * @return array
     */
    public function httpHeaders() : array
    {
        return $this->request->headers->all();
    }

    /**
     * Retrieve agent.
     *
     * @return string
     */
    public function userAgent() : string
    {
        return $this->request->userAgent() ?? '';
    }

    /**
     * Retrieve device's name.
     *
     * @return string
     *
     * @throws \Exception
     */
    public function device() : string
    {
        return $this->getDriverInstance()->device();
    }

    /**
     * Retrieve platform's name.
     *
     * @return string
     *
     * @throws \Exception
     */
    public function platform() : string
    {
        return $this->getDriverInstance()->platform();
    }

    /**
     * Retrieve browser's name.
     *
     * @return string
     *
     * @throws \Exception
     */
    public function browser() : string
    {
        return $this->getDriverInstance()->browser();
    }

    /**
     * Retrieve languages.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function languages() : array
    {
        return $this->getDriverInstance()->languages();
    }

    /**
     * Set visitor (user)
     *
     * @param Model|null $user
     *
     * @return $this
     */
    public function setVisitor(?Model $user)
    {
        $this->visitor = $user;

        return $this;
    }

    /**
     * Retrieve visitor (user)
     *
     * @return Model|null
     */
    public function getVisitor() : ?Model
    {
        return $this->visitor;
    }

    /**
     * Get Country of the Visitor
     * 
     */
    public function country() {
        if($this->geoip != null) {
            $this->geoip->setIp($this->ip());
            return $this->geoip->getCountry();
        } else {
            return 'undefined';
        }
    }

    /**
     * Get Country Code of the Visitor
     * 
     */
    public function countryCode() {
        if($this->geoip != null) {
            $this->geoip->setIp($this->ip());
            return $this->geoip->getCountryCode();
        } else {
            return 'undefined';
        }
    }

    /**
     * Retrieve Unique Visitors for specified Days;
     * If days are not specified '7' days will be used as default
     * 
     * Also, Bot & Crawlers are excluded from unique visitors,
     * may be shown in total page views.
     * 
     * @param int $days
     */
    public function getUniqueVisitors($days = 7) : Int {
        $subDays = Carbon::now()->subDays($days);
        return DB::table(config('visitor.table_name'))
                    ->selectRaw('COUNT(DISTINCT(ip)) as count')
                    ->where('created_at', '>', $subDays)
                    ->where('device', '!=', 'Bot')
                    ->groupBy('ip')->get()->count();
    }

    /**
     * Retrieve All time Vists
     * or specify the days to retrieve for particular time-frame
     * 
     * @param int $days
     */
    public function getAllTimeVists($days = null) : Int {
        $subDays = Carbon::now()->subDays($days);
        
        if($days != null) {
            return DB::table(config('visitor.table_name'))->where('created_at', '>', $subDays)->get()->count();
        } else {
            return DB::table(config('visitor.table_name'))->get()->count();
        }
    }

    /**
     * Create a visit log.
     *
     * @param Model $model
     */
    public function visit(Model $model = null)
    {
        if(in_array($this->request->path(), $this->except)){
            return;
        }  

        $data = $this->prepareLog();

        if (null !== $model && method_exists($model, 'visitLogs')) {
            $visit = $model->visitLogs()->create($data);
        } else {
            $visit = Visit::create($data);
        }

        return $visit;
    }

    /**
     * Retrieve online visitors.
     *
     * @param string $model
     * @param int $seconds
     */
    public function onlineVisitors(string $model, $seconds = 180)
    {
        return app($model)->online()->get();
    }

    /**
     * Determine if given visitor or current one is online.
     *
     * @param Model|null $visitor
     * @param int $seconds
     *
     * @return bool
     */
    public function isOnline(?Model $visitor = null, $seconds = 180)
    {
        $time = now()->subSeconds($seconds);

        $visitor = $visitor ?? $this->getVisitor();

        if (empty($visitor)) {
            return false;
        }

        return Visit::whereHasMorph('visitor', get_class($visitor), function ($query) use ($visitor, $time) {
            $query->where('visitor_id', $visitor->id);
        })->whereDate('created_at', '>=', $time)->count() > 0;
    }

    /**
     * Prepare log's data.
     *
     * Modified: 21 July 2023
     * Added Country and Country Code
     * 
     * @return array
     *
     * @throws \Exception
     */
    protected function prepareLog() : array
    {
        return [
            'method' => $this->method(),
            'request' => $this->request(),
            'url' => $this->url(),
            'referer' => $this->referer(),
            'languages' => $this->languages(),
            'useragent' => $this->userAgent(),
            'headers' => $this->httpHeaders(),
            'device' => $this->device(),
            'platform' => $this->platform(),
            'browser' => $this->browser(),
            'ip' => $this->ip(),
            'country' => $this->country(),
            'country_code' => $this->countryCode(),
            'visitor_id' => $this->getVisitor() ? $this->getVisitor()->id : null,
            'visitor_type' => $this->getVisitor() ? get_class($this->getVisitor()): null
        ];
    }

    /**
     * Retrieve current driver instance or generate new one.
     *
     * @return mixed|object
     *
     * @throws \Exception
     */
    protected function getDriverInstance()
    {
        if (!empty($this->driverInstance)) {
            return $this->driverInstance;
        }

        return $this->getFreshDriverInstance();
    }

    /**
     * Get new driver instance
     *
     * @return Driver
     *
     * @throws \Exception
     */
    protected function getFreshDriverInstance()
    {
        $this->validateDriver();

        $driverClass = $this->config['drivers'][$this->driver];

        return app($driverClass);
    }

    /**
     * Validate driver.
     *
     * @throws \Exception
     */
    protected function validateDriver()
    {
        if (empty($this->driver)) {
            throw new DriverNotFoundException('Driver not selected or default driver does not exist.');
        }

        $driverClass = $this->config['drivers'][$this->driver];

        if (empty($driverClass) || !class_exists($driverClass)) {
            throw new DriverNotFoundException('Driver not found in config file. Try updating the package.');
        }

        $reflect = new \ReflectionClass($driverClass);

        if (!$reflect->implementsInterface(UserAgentParser::class)) {
            throw new \Exception("Driver must be an instance of Contracts\Driver.");
        }
    }
}
