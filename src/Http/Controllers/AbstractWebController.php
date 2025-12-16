<?php
/**
 * Created by PhpStorm.
 * User: kchapple
 * Date: 5/2/19
 * Time: 12:33 PM
 */

namespace ftrotter\ZZermelo\Http\Controllers;

use ftrotter\ZZermelo\Http\Requests\ZZermeloRequest;
use ftrotter\ZZermelo\Interfaces\ZZermeloReportInterface;
use ftrotter\ZZermelo\Models\ZZermeloReport;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

abstract class AbstractWebController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @param ZZermeloReport $report
     * @return mixed
     *
     * Implemnt this method to do any modifications to the report at the controller level.
     * Any view variables you set here will be set on every report.
     */
    public abstract function onBeforeShown(ZZermeloReportInterface $report);

    /**
     * @return mixed
     *
     * Implement this method to specify the blade view template to use
     */
    public abstract function getViewTemplate();

    /**
     * @return mixed
     *
     * Implement this method to specify the report URL path like /ZZermeloCard or /ZZermeloGraph
     */
    public abstract function getReportApiPrefix();


    /**
     * @return string
     *
     * Read the API prefix like `zapi` from the zzermelo config fil
     */
    public function getApiPrefix()
    {
        return api_prefix();
    }

    /**
     * @param ZZermeloRequest $request
     * @return null
     *
     * Default method for displaying a ZZermeloReqest
     * This method builds the report, builds the presenter and returns the view
     */
    public function show(ZZermeloRequest $request)
    {
        $report = $request->buildReport();
        $this->onBeforeShown($report);
        return $this->buildView($report);
    }

    /**
     * @return View
     *
     * Make a view by composing the report with necessary data from child controller
     */
    public function buildView(ZZermeloReportInterface $report)
    {
        // Auth stuff
        $user = Auth::guard()->user();
        if ($user) {
            // Since this is a custom careset column on the database for JWT, make sure the property is set,
            if (isset($user->last_token)) {
                $report->setToken($user->last_token);
            }
        }

        // Get the overall ZZermelo API prefix /zapi
        $report->pushViewVariable('api_prefix', $this->getApiPrefix());

        // Get the API prefix for this report's controller from child controller
        $report->pushViewVariable('report_api_prefix', $this->getReportApiPrefix());

        // Get the view template from the child controller
        $view_template = $this->getViewTemplate();

        if(strlen($view_template) == 0){
            echo "Error: Your zzermelo configuration is likely outdated. sought a view template and got a blank screen";
            exit(1);
        }

        // This function gets both view variables set on the report, and in the controller
        $view_varialbes = $report->getViewVariables();

        // Push all of our view variables on the template, including the report object itself
        $view_varialbes = array_merge($view_varialbes, ['report' => $report]);

        return view( $view_template, $view_varialbes );
    }
}
