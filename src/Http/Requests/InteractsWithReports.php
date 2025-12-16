<?php

namespace ftrotter\ZZermelo\Http\Requests;

use ftrotter\ZZermelo\Models\ReportFactory;
use ftrotter\ZZermelo\ZZermelo;

trait InteractsWithReports
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }

    /**
     * Get the class name of the report being requested.
     *
     * @return mixed
     */
    public function reportClass()
    {
        // report_key is a request parameter defined by the route (we are inside a request object)
        return tap(ZZermelo::reportForKey($this->report_key), function ($report) {
            if(is_null($report)){
			$debug = config('app.debug');
			if($debug){ //lets show the user a specific error
				throw new \ErrorException("ZZermelo returned a null value when trying to create a report from key |$this->report_key| this usually means there is no existing report by that name");
			}else{
				//in a production environment, we just show a 404 message
				abort(404);
			}
		}
        });
    }

    /**
     * Get a new instance of the resource being requested.
     *
     * @return \ftrotter\ZZermelo\Models\ZZermeloReport
     */
    public function buildReport()
    {
        // Get the report class by the report_key request parameter, or fail with 404 Not Found
        $reportClass = $this->reportClass();

        // Build a new instance of $reportClass using the found class, and THIS request
        // (this trait is for requests that interact with reports)
        return ReportFactory::build( $reportClass, $this );
    }
}
