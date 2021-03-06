<?php
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once ('ReportFilterHelpers.php');
require_once ('app/controllers/ReportsController.php');
require_once('Zend/View.php');
require_once('Zend/Auth.php');
require_once('models/analytics/MetricClient.php');
require_once 'models/analytics/AnalyticsDashboard.php';
require_once('models/table/Helper2.php');
/**
 * This class is used by the analytics view to retrieve and push data to mongodb
 * database.
 */
class AnalyticsqueryController extends ReportFilterHelpers {
    
    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array())
    {
        parent::__construct ( $request, $response, $invokeArgs );
    }
    
    public function init() 
    {
        //parent::init();
    }
    
    public function checkLoginAction() {
		parent::preDispatch ();
                
                 $this->_helper->viewRenderer->setNoRender();
                     
                $response = array();
                
		if (! $this->isLoggedIn ()){
                     
                    $response["ok"] = 0;
                    $response["dashboardUrl"] = $this->getDashboardUrl();
                }
                else{
                    $response["ok"] = 1;
                    $response["dashboardUrl"] = $this->getDashboardUrl();
                }
                
                echo json_encode($response);

    }
    
    public function getDashboardUrl(){
        
        return Settings::$COUNTRY_BASE_URL;
        
    }
    
    //This method gets the total registered users in fpdashboard
    public function getTotalRegisteredUsersAction() {
        
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList,$tierText) = $this->buildParameters();
        
        $query = array(
            array('$unwind'=>'$'.$tierText),
            array('$match'=>array($tierText=>array('$in'=>$geoList)))//array('province_id'=>array('$ne'=>'0'));
        );
        $projection = array();
        $keyword = "aggregate"; //find
        $table = "users";
        
        $metric_client = new MetricClient();
        $cursor = $metric_client->handleDataGet($query,$projection,$keyword,$table);
        
        //var_dump(json_decode($cursor)[0]); exit();
        
        echo count(json_decode($cursor));
    }
        
//    public function getLocationsAction()
//    {
//        $this->_helper->viewRenderer->setNoRender();
//
//        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
//
//        $sql = "select id, location_name, tier, parent_id from location order by location_name asc";
//
//        $query = $db->query($sql);
//        $result = $query->fetchAll();
//
//        echo json_encode($result);
//    }
    
    public function getUserprofileAction() {
        
        $this->_helper->viewRenderer->setNoRender();
                
        $dashboard = new AnalyticsDashboard();
        
        list($geoList,$tierText) = $this->buildParameters();
        
        $mode = $this->getSanParam('mode');
        
        $mode = isset($mode)?$mode:'filter';
        
        $cursorJson = $dashboard->fetchUserprofile($geoList, $tierText, $mode);
        
        //var_dump(json_decode($cursorJson,TRUE));
        echo $cursorJson;
    }
    
    
    public function dumpAllUsersAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        $locationsArray = array(); $usersArray = array();
        
        //get all locations
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select ()->from (array ('l' => 'location' ), array ('id','location_name'));
        $result = $db->fetchAll($select);
        foreach ($result as $location)
            $locationsArray[$location['id']] = $location['location_name'];
        
        //get all users
        //$db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from (array ('u' => 'user' ), array ('*'));
        $usersArray = $db->fetchAll($select);
        $runArray = array();
        $multipleLocaitonsString = "";
        
        foreach($usersArray as $key=>$user){
            $multipleLocaitonsString = "";
            
            if($user['role'] == "3"){
                
                $user['geozone'] = array();
                $user['state'] = array();
                
                $compoundLocationsArray = json_decode($user['multiple_locations_id']);
                
                //no multiple_locations_id entry for this PARTNER user
                if(empty($compoundLocationsArray)) { 
                    unset($usersArray[$key]);
                    continue; 
                }
                
                foreach ($compoundLocationsArray as $compoundLocation){
                    $locations = explode('_', $compoundLocation);
                    //ensure you are not repeating the zone. Some users have multiple states but one geozone
                    if(!in_array($locationsArray[$locations[0]], $user['geozone'])) 
                            $user['geozone'][] = $locationsArray[$locations[0]];
                    
                    $user['state'][] = $locationsArray[$locations[1]];
                    
                    $multipleLocaitonsString .= $locationsArray[$locations[0]] . "_" . $locationsArray[$locations[1]] . ",";
                }
            }
            else
            {
                if($user['province_id'] != 0) {$user['geozone'] = $locationsArray[$user['province_id']]; }
                if($user['district_id'] != 0) $user['state'] = $locationsArray[$user['district_id']];
                if($user['region_c_id'] != 0) $user['lga'] = $locationsArray[$user['region_c_id']];
            }
            
            $multipleLocaitonsString = substr($multipleLocaitonsString, 0, -1);
            $user['multiplelocation'] = $multipleLocaitonsString;
            
            $runArray[] = $user;
        }
        
        //var_dump($runArray); exit;
        
        $metric_client = new MetricClient();
        $response = $metric_client->handleDataDump($runArray, 'users');
        var_dump($response);  
        
    }

    
    
    public function dumpAllLocationAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        
        $sql = "select id 'id', location_name, tier, parent_id from location order by location_name asc";
        
        $query = $db->query($sql);
        $result = $query->fetchAll();
        
        
        
        if($result)
        {
            
            $metric_client = new MetricClient();
            $response = $metric_client->handleDataDump($result, 'locations');
            var_dump($response);
        }    

    }
    
    
 
    
    public function getLocationDataAction(){
       $this->_helper->viewRenderer->setNoRender();
       
       //handleDataGet
       
//       $query = array(
//           array('$match'=>array('_id'=>'1814')),
//           array('$lookup' => array(
//               "from"=>"users",
//               "localField"=>"_id",
//               "foreignField"=>"geozone",
//               "as"=>"users"
//           )),
//           array('$limit'=>1)
//       );
//       $keyword = "aggregate";
       
       $query = array();
       $projection = array();
       $keyword = "find";
       $table = "locations";
       
       $metric_client = new MetricClient();
       $cursor = $metric_client->handleDataGet($query,$projection,$keyword,$table);
       
       echo $cursor;  
    }
    
    
    public function getUserLoginsAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        date_default_timezone_set("Africa/Lagos");
        
        $yday = date("Y-m-d",strtotime("-1 days"));
        $yday = "" .strtotime(date($yday . " 00:00:00")) * 1000;
        $today = "" . strtotime(date("Y-m-d 00:00:00")) * 1000;
        
        //var_dump($yday); var_dump($today); exit;
        
        $queryArray = array(
                        'action_type'=>  MetricClient::ACTION_TYPE_LOGIN,
                        'millidate' => array('$gte'=>$yday, '$lt'=>$today)
                );
        
        $optionsArray = array();
        $operationKeyword = 'find';
        $tableName = 'metrics';
        
                
        $metricClient = new MetricClient();
        $cursorJson = $metricClient->handleDataGet($queryArray, $optionsArray, $operationKeyword, $tableName);
        
        $jsonData = json_decode($cursorJson,TRUE);
        $usersIdList = array();
        
        $loginsCount = 0;
        
        for($i=0; $i < count($jsonData); $i++){
            
            if(!in_array($jsonData[$i]['userid'],$usersIdList)){
                $usersIdList[] = $jsonData[$i]['userid'];
                $loginsCount++;
            }
        }
        
        
        echo $loginsCount;

    }
    
    
    
    public function getUserDailySessionsAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        date_default_timezone_set("Africa/Lagos");
        
        $yday = date("Y-m-d",strtotime("-1 days"));
        $yday = "" .strtotime(date($yday . " 00:00:00")) * 1000;
        $today = "" . strtotime(date("Y-m-d 00:00:00")) * 1000;
        
       
        $metricClient = new MetricClient();
        
        $query = array(
//                    array('$match'=>array('action_type'=>  MetricClient::ACTION_TYPE_LOGIN,'millidate' => array('$gte'=>$yday, '$lte'=>$today))),
                    array('$match'=>array('millidate' => array('$gte'=>$yday, '$lte'=>$today))),
                    array('$sort'=>array('userid'=>1,'millidate'=>-1))
                );
        
         $cursorJson = $metricClient->handleDataGet($query,array(),'aggregate','metrics');

                $jsonData = json_decode($cursorJson,TRUE);
                $sessions = 0;
                $todayMetrics = array(); // This array holds user id, and millidate 
                
                 for($i=0; $i < count($jsonData)-2; $i++)
                 {
                     if(isset($jsonData[$i]['userid']) && isset($jsonData[$i+1]['userid'])
                             && isset($jsonData[$i]['millidate']) && isset($jsonData[$i+1]['millidate']))
                    {
                         
                             if($jsonData[$i]['action_type'] == 3)
                             {
                                 $sessions++;
                             }
                             elseif($jsonData[$i]['action_type'] == 4)
                             {
                                 $sessions++;
                             }
                             else{
                                 
                                 $tempArray = array(); // holds a particular user metrics-millidate
                                 
                                 foreach($todayMetrics as $key=>$val){
                                     
                                     if($val["id"] == $jsonData[$i]['userid']){
                                         $tempArray[] = $val["millidate"];
                                     }
                                     
                                 }
                                 
                                 $lastLogin = end($tempArray); //Get the users last metric action
                                 
                                 
                                 if(($jsonData[$i]['millidate'] - $lastLogin) >= 60000){
                                     $sessions++;
                                 }
                             }
                             
                             $todayMetrics[] = array("id"=>$jsonData[$i]['userid'],"millidate"=>$jsonData[$i]['millidate']);
                     }
                     
                 }
        
                 
                 echo $sessions;
                 
    }
    
    public function getCurrentActiveUsersAction() {
        $this->_helper->viewRenderer->setNoRender();
        
        date_default_timezone_set("Africa/Lagos");
        
        $yday = date("Y-m-d",strtotime("-1 days"));
        $yday = "" .strtotime(date($yday . " 23:00:00")) * 1000;
        $today = "" . strtotime(date("Y-m-d 23:59:59")) * 1000;
        
        $now = time() * 1000;
        
        $dashboard = new AnalyticsDashboard();
        list($year, $month) = $dashboard->getYearMonth();
        
        $metricClient = new MetricClient();
        
        $query = array(
                    array('$match'=>array('month'=>$month,'year'=>$year . '','millidate' => array('$gte'=>$yday, '$lte'=>$today))),
                    array('$sort'=>array('userid'=>1,'millidate'=>-1))
                );
        
        
        $cursorJson = $metricClient->handleDataGet($query,array(),'aggregate','metrics');
        
        
        $activeUsersArr = json_decode($cursorJson,TRUE);
        
        $active = 0;
        $match = null;
        
        foreach($activeUsersArr as $k=>$val)
        {
            
                $userid = $val['userid'];
                
                if($userid == $match)
                        continue;
                else
                {
                    if(!isset($activeUsersArr[$k+1]['userid']))
                        continue;
                    if($userid == $activeUsersArr[$k+1]['userid'])
                    {
                        
                        $match = $userid;
                        
                        $diff = $now - $val['millidate'];
                        
                        if($diff <= 1800000)
                        {
                            $active += 1;
                        }
                    }
                }
           
        }
        
        echo $active;
    }
    
    
    function getUserSessionHistoryAction(){
         $this->_helper->viewRenderer->setNoRender();
         $metricClient = new MetricClient();
         
         date_default_timezone_set("Africa/Lagos");
       
        
         
         $userid = $this->getSanParam('userid');
         $from = $this->getSanParam('from');
         $to = $this->getSanParam('to');
         if($from != null && $from != '' && $to != '' && $to != null)
         {
             
             $from .= ' 00:00:00';
             $to .= ' 23:59:59';
             $fromDate = strtotime($from) * 1000;
             $toDate = strtotime($to) * 1000;
             
            
             
            $query = array(
            array('$match'=>array('userid'=>$userid,'millidate'=>array('$gte'=>$fromDate.'','$lte'=>$toDate.''))),
            array('$sort'=>array('millidate'=>-1))
            );
         }
         else
         {
             $query = array(
                array('$match'=>array('userid'=>$userid)),
                array('$sort'=>array('millidate'=>-1))
            );
         }
          
            
        $cursorJson = $metricClient->handleDataGet($query, array(), 'aggregate', 'metrics');
        
        $cursorArray = json_decode($cursorJson,TRUE);
        
        
        //Grouping the hits into sessions
        $sessionsGroup = array();
        
        $tempArr = array();
        $arrSize = count($cursorArray);
        $count = 1;
        
        foreach($cursorArray as $k=>$val)
        {
            if($val['action_type'] == 3 || $count == $arrSize)
            {
                $tempArr[] = $val;
                
                 
                $sessionsGroup[] = array_reverse($tempArr);
                $tempArr = array();
            }
            else
            {
              $tempArr[] = $val;    
            }
            
            $count++;
        }
        
        
        //Adding activities, and Page name to each session in a SessionGroupArray
        foreach($sessionsGroup as $k=>$value)
        {
          foreach($value as $k2=>$val) 
          {
                if($val['action_type'] == 2)
                {
//                    $str = print_r($val['details'],true);
//                    $str = str_replace('Array', '', $str);
//                    $str = str_replace('[0] =>', '', $str);
//                    $str = str_replace(array('[',']','(',')'), '', $str);
                    $sessionsGroup[$k][$k2]['activities'] = 'Filter';
                }
                else
                {
                    $sessionsGroup[$k][$k2]['activities'] = "Visit";
                }
                
                if(isset($val['page_id']))
                {
                    $sessionsGroup[$k][$k2]['page'] = $this->getPageName($val['page_id']); 
                }
                else
                {
                    $sessionsGroup[$k][$k2]['page'] = "Login";
                }
          }
        }
        
        //Adding LoginTime, LogoutTime, and Session Duration to each SessionGroupArray
        
        foreach ($sessionsGroup as $k=>$value)
        {
            $arrSize = count($value);
            $count = 1;
            $startTime = 0;
            $endTime = 0;
            foreach ($value as $k2=>$val)
            {
                if($count == 1)
                {
                    $sessionsGroup[$k]['loginTime'] = $val['timestamp'];
                    $startTime = $val['timestamp'];
                }
                
                if($count == $arrSize)
                {
                    $sessionsGroup[$k]['logoutTime'] = $val['timestamp'];
                    $endTime = $val['timestamp'];
                }
                
                $count++;
            }
            
            $duration = $this->getDateDiff($startTime, $endTime);
            $sessionsGroup[$k]['duration'] = $duration;
        } 
        
        
          //var_dump($sessionsGroup);
            echo json_encode($sessionsGroup);
    }
    
     public function getUserDetailsAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        date_default_timezone_set("Africa/Lagos");
       
        $now = time() * 1000;
        
        $metricClient = new MetricClient();
        
        $userid = $this->getSanParam('userid');
        
        $query = array(
            array('$match'=>array('id'=>$userid.'')),
            array('$lookup'=>array(
                'from'=>'metrics',
                'localField'=>'id',
                'foreignField'=>'userid',
                'as'=>'metrics'
            )),
            array('$unwind'=>array('path'=>'$metrics','preserveNullAndEmptyArrays'=>true)),
            array('$sort'=>array('id'=>1,'metrics.millidate'=>-1))
        );
        
        $cursorJson = $metricClient->handleDataGet($query, array(), 'aggregate', 'users');
        
        $cursorArray = json_decode($cursorJson,TRUE);
        
        
        $metrics = array();
        $users = array();
        $profile = array();
        
        $count = 0;
            foreach ($cursorArray as $k=>$value)
            {
              
                        
                $metrics[] = isset($value['metrics'])?$value['metrics']:"";

                if($count == 0)
                {
                    //$metrics = isset($value['metrics'])?$value['metrics']:"";
                    
                    $zone = (isset($value['geozone']))?$value['geozone']:"";
                    $state = (isset($value['state']))?$value['state']:"";
                    
                    $location = $this->prepareUserLocation($value['multiplelocation'],$zone,$state);
                    $fullname = ucfirst(strtolower($value['last_name'])) . " " . ucfirst(strtolower($value['first_name']));

                    $profile[] = array('fullname'=>$fullname,'location'=>$location,'email'=>$value['email']);
                }
                $count++;
            }
            
            
            //Getting LoggedInAction
            $loggedInMetrics = array();
            $sessions = 0;
            foreach ($metrics as $k=>$val)
            {
                
                $actionType = isset($val['action_type'])?$val['action_type']:'-1';
                
                if($actionType == 3 || $actionType == '3')
                {
                     // $loggedInMetrics[] = $val;
                     $sessions++; 
                }
                elseif(isset($metrics[$k - 1])){
                    
                    $activityTimeDifference = $val['millidate'] - $metrics[$k -1]['millidate'];
                    
                    if($activityTimeDifference > 600000 ){
                        $sessions++;
                    }
                }
            }
            
            //Checking if user is logged in
            $loggedIn = "No";
            
            foreach($metrics as $k=>$val)
            {
                $millidate = isset($val['millidate'])?$val['millidate']:0;
                
                $timeDiff = $now - $millidate;
                
                if($timeDiff <= 900000)
                       $loggedIn = "Yes";
                
                break;
            }
            
            $users['loggedIn'] = $loggedIn;
            $users['sessions'] = $sessions ;//count($loggedInMetrics);
            $users['metrics'] = $metrics;
            $users['profile'] = $profile;
            
            //var_dump($users);
            //var_dump($metrics);
            //var_dump(json_decode($cursorJson,TRUE));
            
            echo json_encode($users);

    }
    
    public function getDateDiff($date1,$date2){
        $date_a = new DateTime($date1);
        
        $date_b = new DateTime($date2);

        $interval = date_diff($date_a,$date_b);

        return $interval->format('%h:%i:%s');
    }
    
    public function getPageName($pageid){
        $pages = array(
            'Trained HWs'=>'charts_coverage_cummhwtrained',
            'Facilities with Trained HWs'=>'charts_coverage_percentfacswithtrainedhw',
            'Facilities Providing FP'=>'charts_coverage_percentfacsproviding',
            'Facilities providing FP over time'=>'charts_coverage_providingovertime',
            'Facilities with trained HWs providing FP'=>'charts_coveragefacswithhwproviding',
            'Facilities with Trained HWs providing FP over time'=>'charts_coverage_coverageovertime',
            'Commodity Consumption'=>'charts_consumption_consumption',
            'Commodity Consumption Demo'=>'charts_consumption_consumptiondemo',
            'New FP acceptors and current FP users'=>'charts_consumption_newandcurrentfpusers',
            'stock outs at facilities with trained HWS'=>'charts_stockout_percentstockoutwithtrainedhw',
            'Stock out at facilities providing FP'=>'charts_stockout_percentfacsprovidingbutstockedout',
            'stock outs at facilities providing FP over time'=>'charts_stockout_stockouts',
            'Facilities with HWs providing FP'=>'charts_coverage_facswithhwproviding',
            'Facilities providing FP over time demo'=>'charts_coverage_providingovertimedemo',
             'Facility Data Report'=>'reports_reports_facilitysummary',
             'Training Data Report'=>'reports_reports_trainingrep',
             'All Data Report'=>'reports_reports_alldata',
             'View/Edit Training Location'=>'reports_facility_searchLocation',
             'View/Edit Facility'=>'reports_facility_search',
             'View/Edit Person'=>'reports_person_view',
             'View/Edit Trainings'=>'reports_training_view',
             'All Queries Report'=>'reports_reports_allqueriesresult',
             'Training Report'=>'reports_reports_training_result',
             'Training Report Demo'=>'reports_reports_trainingrepdemo',
             'Reports Summary'=>'reports_reports_summaryresult',
             'Facility Summary Report'=>'reports_reports_facilitysummarydemo',
             'Reports Archived'=>'reports_reports_archivedreports',
             'Archieved PDF Report'=>'reports_pdf_archivedreports',
             'Add New Training'=>'dc_training_add',
            'Add New Person'=>'dc_person_add',
            'Add Training Location'=>'dc_facility_addLocation',
            'Edit Person'=>'dc_person_edit',
            'Import Training'=>'dc_training_import',
            'Person View'=>'dc_person_view',
            'Person History View'=>'dc_person_history',
            'Person Search'=>'dc_person_search',
            'Home'=>'_index_index',
            'Training View'=>'dc_training_view',
            'Training View Index'=>'dc_training_index',
            'Training Search'=>'dc_training_search',
            'Training Report View'=>'reports_reports_trainingresult',
            'Training Delete Confirm'=>'dc_training_deleted',
            'Training Delete'=>'dc_training_deletetraining',
            'Training Edit'=>'dc_training_edit',
            'User Search'=>'_user_search',
            'User List'=>'_user_list',
            'User Edit'=>'_user_edit',
            'User Account'=>'_user_myaccount',
            'User Login'=>'_user_login',
            'User Index'=>'_user_index'
            
        );
        
        
        $pagename = '';
        $v = '';
        
        foreach($pages as $k=>$val)
        {
            
            if(trim($val) === trim($pageid))
            {
                $pagename = $k;
                break;
            }
        }
        
        if($pagename == ''){
            
             return $pageid;
        }
        
        if($pagename == 'Training View Index'){
            $pagename = 'Training View';
        }
        
        return $pagename;
    }

    
    public function getUsersDetailTableAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        date_default_timezone_set('Africa/Lagos');
        
        $metricClient = new MetricClient();
        
        list($geoList, $tierText) = $this->buildParameters();
        
        
        
        if($tierText == 'geozone' && count($geoList) == 6) {
            
            
            
            $query = array(
                array('$match'  => array($tierText => array('$in'=>$geoList))),
                array('$lookup'=>array(
                    'from'=>'metrics',
                    'localField'=>'id',
                    'foreignField'=>'userid',
                    'as'=>'metrics'
                ))
//                ,
//                array('$unwind'=>array('path'=>'$metrics','preserveNullAndEmptyArrays'=>true)),
//                array('$sort'=>array('metrics.millidate'=>-1,'id'=>1))
            );
            
            
        }
        else {
            $query = array(
                array('$match'  => array($tierText => array('$in'=>$geoList))),
                array('$lookup'=>array(
                    'from'=>'metrics',
                    'localField'=>'id',
                    'foreignField'=>'userid',
                    'as'=>'metrics'
                ))
//                ,
//                array('$unwind'=>array('path'=>'$metrics','preserveNullAndEmptyArrays'=>true)),
//                array('$sort'=>array('metrics.millidate'=>-1,'id'=>1))
            );
        }
        
        $cursorJson = $metricClient->handleDataGet($query, array(), 'aggregate', 'users');
        
        $cursorArray = json_decode($cursorJson,TRUE);
        
        //print_r($cursorArray[0]); exit();
        
//        var_dump($cursorArray[0]); 
//        exit();
        
        $userArray = array();
        $tempArray = array();
        $tempMetrics = array();
        
        foreach($cursorArray as $k=>$record){
            
            $metrics = $record['metrics'];
            
            if(isset($record['multiplelocation'])){
                $loc = $record['multiplelocation'];
            }
            else{
                $loc = false;
            }
            
            $location = $this->prepareUserLocation($loc, isset($record['geozone'])?$record['geozone']:"", isset($record['state'])?$record['state']:"");
            $fullname = $record['last_name'] . ' ' . $record['first_name'];
            $role = $this->getUserRole($record['role']);
            
            $sessionCount = 0;
            $sessionTimeRange = '';
            $lastLogin = '';
            $loginStatus = 'NO';
            $lastLoginMilli = 0;
            
            
            foreach($metrics as $k=>$metric){
               
                     
                     if($metric['action_type'] == 3)
                     {
                          $sessionCount++;
                     }else{
                         $nextLogin = isset($metrics[$k + 1]['millidate'])?$metrics[$k+1]['millidate']:0;
                         $loginTime = isset($metric['millidate'])?$metric['millidate']:0;
                         
                         if($nextLogin != 0){
                             if(($nextLogin - $loginTime) >= 60000){
                                 //$sessionCount++;
                             }
                         }
                     }
                     
                     if($k === 0)
                     {
                         $sessionTimeRange = $metric['timestamp'];
                         $lastLoginMilli = isset($metric['millidate'])?$metric['millidate']:0;
                         
                         if($metric['action_type'] == 3)
                         {
//                             $lastLogin = $sessionTimeRange;
                         }
                         $lastLogin = $sessionTimeRange;
                     }
                     else if($k == count($metrics)-1)
                     {
                         $sessionTimeRange .=  ' - ' . $metric['timestamp'];
                         
                         if($lastLogin == '' && $metric['action_type'] == 3)
                         {
//                             $lastLogin = $outer['metrics'][$i]['timestamp'];
                         }
                         $lastLogin = $metric['timestamp'];
                     }
                     else
                     {
                         if($lastLogin == '' && $metric['action_type'] == 3)
                         {
                             $lastLogin = $metric['timestamp'];
                         }
                       
                     }
            }
            
                $now = time() * 1000;
                
                if($now - $lastLoginMilli <= 900000)
                {
                    $loginStatus = "Yes";
                }
                
                $newUserArray[] = array('fullname'=>$fullname,'location'=>$location,'role'=>$role,
                    'sessions'=>$sessionCount,'sessionsRange'=>$sessionTimeRange,'lastLogin'=>$lastLogin,'loginStatus'=>$loginStatus);
            
        }
        
        echo json_encode($newUserArray);
    }
    
    
    public function getRecentDcEventAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        $metricClient = new MetricClient();
        
        $query = array(
            array('$match'=>array('action_type'=>5)),
            array('$lookup'=>array(
                'from'=>'users',
                'localField'=>'userid',
                'foreignField'=>'id',
                'as'=>'user'
            )),
            array('$sort'=>array('millidate'=>-1))
        );
        
        $cursorJson = $metricClient->handleDataGet($query,array(),'aggregate','metrics');
        
        $cursorArray = json_decode($cursorJson,TRUE);
        
       
        $userDcArray = array();
        
        foreach($cursorArray as $k=>$cursor)
        {
            $dcActivity = '';
            $dcTime = '';
            $dcDetails = '';
            $userLocation = '';
            $userFullname = '';
            
            //Checking if its a successful Upload/Save
            if(isset($cursor['training_id']) || isset($cursor['person_id']))
            {
                if(isset($cursor['activity_type']))
                {
                    switch($cursor['activity_type'])
                    {
                        case 'eu':
                            $dcActivity = 'Excel Upload';
                            $dcDetails = '<a href="'.Settings::$COUNTRY_BASE_URL . "/training/view/id/". $cursor['training_id'].'">Training ID : ' .  $cursor['training_id'] . "</a>";
                            break;
                        case 'ta':
                            $dcActivity = 'Training Add';
                            $dcDetails = '<a href="'.Settings::$COUNTRY_BASE_URL . "/training/view/id/". $cursor['training_id'].'">Training ID : ' .  $cursor['training_id'] . "</a>";
                            break;
                        case 'pa':
                            $dcActivity = 'Person Add';
                            //NB: please $cursor['training_id'] is meant to be $cursor['person_id']
                            $dcDetails = '<a href="'.Settings::$COUNTRY_BASE_URL . "/person/view/id/". $cursor['training_id'].'">Person ID : ' . $cursor['training_id'] .  "</a>";
                            break;
                    }
                }
                
                $dcTime = $cursor['timestamp'];
                
                //Getting users location, by passing multiplelocation, geozone, and state to locationMethod
                
                $multiplelocation = isset($cursor['user'][0]['multiplelocation'])?$cursor['user'][0]['multiplelocation']:'';
                $geozone = isset($cursor['user'][0]['geozone'])?$cursor['user'][0]['geozone']:'';
                $state = isset($cursor['user'][0]['state'])?$cursor['user'][0]['state']:'';
                
                $userLocation = $this->prepareUserLocation($multiplelocation, $geozone, $state);
                
                //Getting user fullname
                if(isset($cursor['user'][0])){
                    $userFullname = $cursor['user'][0]['last_name'] . ' ' . $cursor['user'][0]['first_name'];
                }
                else{
                    $userFullname = "";
                }
                $temp = array('fullname'=>$userFullname,'activity'=>$dcActivity,'details'=>$dcDetails,'location'=>$userLocation,'time'=>$dcTime);
                
                $userDcArray[] = $temp;
            }
        }
        
        
        echo json_encode($userDcArray);
    }
                        
    public function test2Action(){
        
        $this->_helper->viewRenderer->setNoRender();
        
        date_default_timezone_set('Africa/Lagos');
        
        $yesterday = strtotime(date('d-m-Y',strtotime("-1 days"))) * 1000;
        $today = strtotime(date('d-m-Y 00:00:00')) * 1000;
        
        
        $metricClient = new MetricClient();
        
        list($geoList,$tierText) = $this->buildParameters();
        
        $cursorJson = $metricClient->handleDataGet(array(),array(),'find','users');
        var_dump($cursorJson);
        
    }
    
    public function testAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        date_default_timezone_set('Africa/Lagos');
        
        $yesterday = strtotime(date('d-m-Y',strtotime("-1 days"))) * 1000;
        $today = strtotime(date('d-m-Y 00:00:00')) * 1000;
        
        
        $metricClient = new MetricClient();
        
        list($geoList,$tierText) = $this->buildParameters();
        
        $query = array(
            //'action_type'=>3,'millidate'=>array('$gte'=>$yesterday.'')
           array('$match'=>array
                   (
                       '$and'=>array(
                           array('action_type'=>3),
                           array('millidate'=>array('$gte'=>$yesterday.'','$lt'=>$today.'')),
                           array('userid'=>array('$ne'=>'19'))
                           )
                   )
               ),
            array(
                '$lookup'=>array(
                     'from'=>'users',
                     'localField'=>'userid',
                     'foreignField'=>'id',
                     'as'=>'user'
                     
                 )
            )
        );
        
        $cursorJson = $metricClient->handleDataGet($query,array(),'aggregate','metrics');
        
        $cursorArray = json_decode($cursorJson,TRUE);
        
    }
    public function getUserRole($roleId){
        $roles = array('1'=>'Administrator','2'=>'FMOH','3'=>'Partner','4'=>'State','5'=>'LGA');
        
        foreach ($roles as $key => $value) 
        {
            if($key == $roleId)
            {
                return $value;
            }
        }
        
        return '';
    }
    
    public function prepareUserLocation($location,$geozone,$state){
        if($location == false || $location == "")
        {
            if(!empty($geozone)  && !empty($state))
            {
                return $geozone . ' > ' . $state;
            }
            else
            {
                return '';
            }
        }
        
        $locations = explode(',',$location);
        
        $locationString = "";
        
        foreach ($locations as $loc)
        {
            $str = explode('_',$loc);
            $locationString .= $str[0] . ' > ' . $str[1] . ',';
        }

        $newLocationString = substr($locationString, 0, strlen($locationString)-1);
        
        return $newLocationString;
    }
    /**************** MODEL FUNCTIONS *************************/
    
    /**
     * FUNCTIONS IN THIS SECTION WILL
     * 1. Get geolist and tier text
     * 2. call the model function that 
     *      a. constructs the query, 
     *      b. calls the SOAP funciton and 
     *      c. returns result in json format
     * 3. return result to view
     */
    
    /**
     * This function will get the user logins by location
     */
    public function getUserLoginsByLocationAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        $helper = new Helper2();
        $latestPullDate = $helper->getLatestPullDate();
        //echo json_encode($_POST['geozone']); exit; 
        
        //get the selected parameters
        list($geoList, $tierText) = $this->buildParameters(); //blids the same method in ReportFilterHelpers
        //echo json_encode($geoList);echo $tierText; exit;
        
        $dashboard = new AnalyticsDashboard();
        $jsonResponse = $dashboard->fetchUserLoginsByLocation($geoList, $tierText,true);
        
        list($year, $month) = $dashboard->getLastMonthAndYear();
        
        $returnArray = array(
                                'data'=>json_decode($jsonResponse), 
                                'month'=>$month,
                                'year'=>$year
                            );
        
        echo json_encode($returnArray);
    }
    
    
    //Run method to cache Overview module line chart
    public function cacheLocationByLoginsDataAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters();
        
        $dashboard = new AnalyticsDashboard();
        $jsonResponse = $dashboard->fetchUserLoginsLastMonthsByLocation2($geoList, $tierText);
        
        echo $jsonResponse;
        
    }
    
    //DashBoard Overview Line-Chart method
    public function getUserLoginsLastMonthsByLocationAction(){
        $this->_helper->viewRenderer->setNoRender();
        
      
        list($geoList, $tierText) = $this->buildParameters();
        
        $dashboard = new AnalyticsDashboard();
        $jsonResponse = $dashboard->fetchUserLoginsLastMonthsByLocation($geoList, $tierText);
        
        echo $jsonResponse;
    }
    
    //DashBoard Overview Table
    public function getDetailsByLocationAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters(); //blids the same method in ReportFilterHelpers
        
        $dashboard = new AnalyticsDashboard();
        
        $jsonResponse = $dashboard->fetchDetailsByLocation($geoList,$tierText);
        
        echo json_encode($jsonResponse);
    }
    
    
    //Ajax Action method for charts under modules
     public function getDailySessionsByChartSubButtonAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters(); 
        
        $dashboard = new AnalyticsDashboard();
        
        $cursonJson = $dashboard->fetchDailySessionsByChartSubButton($geoList,$tierText);
       
         echo $cursonJson;
        
        
    }
    
    public function getDailySessionsByQueriesAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters(); 
        
        $dashboard = new AnalyticsDashboard();
        
        $cursonJson = $dashboard->fetchDailySessionsByQueries($geoList,$tierText);
       
         echo $cursonJson;
    }
    
    
    public function getDailySessionsByDataCollectionAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters(); 
        
        $dashboard = new AnalyticsDashboard();
        
        $cursonJson = $dashboard->fetchDailySessionsByDataCollection($geoList,$tierText);
       
         echo $cursonJson;
    }
    
    public function cacheDailySessionsLastMonthsByChartsAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters(); 
        
        $dashboard = new AnalyticsDashboard();
        
        $cursonJson = $dashboard->cacheDailySessionsLastMonthsByCharts($geoList,$tierText);
        
        echo $cursonJson;
        //var_dump(json_decode($cursonJson),TRUE);
    }
    
    public function getDailySessionsLastMonthsByChartsAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters(); 
        
        $dashboard = new AnalyticsDashboard();
        
        $cursonJson = $dashboard->fetchDailySessionsLastMonthsByCharts($geoList,$tierText);
        
        echo $cursonJson;
        //var_dump(json_decode($cursonJson),TRUE);
    }
    //cacheDailySessionsLastMonthsByQueries
    
    public function cacheDailySessionsLastMonthsByQueriesAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters(); 
        
        $dashboard = new AnalyticsDashboard();
        
        $cursonJson = $dashboard->cacheDailySessionsLastMonthsByQueries($geoList,$tierText);
        
        echo $cursonJson;
        //var_dump(json_decode($cursonJson),TRUE);
    }
    
    public function getDailySessionsLastMonthsByQueriesAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters(); 
        
        $dashboard = new AnalyticsDashboard();
        
        $cursonJson = $dashboard->fetchDailySessionsLastMonthsByQueries($geoList,$tierText);
        
        echo $cursonJson;
        //var_dump(json_decode($cursonJson),TRUE);
    }
    
    
    public function cacheDailySessionsLastMonthsByDcAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters(); 
        
        $dashboard = new AnalyticsDashboard();
        
        $cursonJson = $dashboard->cacheDailySessionsLastMonthsByDC($geoList,$tierText);
        
        echo $cursonJson;
        //var_dump(json_decode($cursonJson),TRUE);
    }
    
    public function getDailySessionsLastMonthsByDcAction(){
        $this->_helper->viewRenderer->setNoRender();
        
        list($geoList, $tierText) = $this->buildParameters(); 
        
        $dashboard = new AnalyticsDashboard();
        
        $cursonJson = $dashboard->fetchDailySessionsLastMonthsByDC($geoList,$tierText);
        
        echo $cursonJson;
        //var_dump(json_decode($cursonJson),TRUE);
    }
    
    //Users module section
    
    public function getSumTotalUsersByGeoAction() {
        $this->_helper->viewRenderer->setNoRender();
        
        $dashboard = new AnalyticsDashboard();
        
        list($geoList,$tierText) = $this->buildParameters();
        
        $cursorArray = $dashboard->fetchSumTotalUsersByGeo($geoList, $tierText);
        
        echo json_encode($cursorArray);
    }
    
    //cacheSumTotalUsersLast12Months
    
    public function cacheSumTotalUsersLast12MonthsAction(){
        $this->_helper->viewRenderer->setNoRender();
      
        $dashboard = new AnalyticsDashboard();
        
        list($geoList, $tierText) = $this->buildParameters();
        
        $cursorArray = $dashboard->cacheSumTotalUsersLast12Months($geoList, $tierText);
        
        echo json_encode($cursorArray);
        
    }
    
    public function getSumTotalUsersLast12MonthsAction(){
        $this->_helper->viewRenderer->setNoRender();
      
        $dashboard = new AnalyticsDashboard();
        
        list($geoList, $tierText) = $this->buildParameters();
        
        $cursorArray = $dashboard->fetchSumTotalUsersLast12Months($geoList, $tierText);
        
        echo json_encode($cursorArray);
        
    }
    
   
    /*TP:
         * This method will operate on the post variable from form and 
         * construct the right parameters to be used for calls to the model methods
         * This method should be usable by all controllers
         * Region --> LGA
         * District --> State
         * Province --> Geo Zone
         */
        public function buildParameters($useLimit = TRUE){
            $selectionLimit = 6;
            $geoList=array();
            $tierValue = 0; $tierText = '';
            $helper = new Helper2();
                
            if( isset($_POST["region_c_id"]) && 
                (count($_POST["region_c_id"])>1 ||
                (count($_POST["region_c_id"])==1 AND !empty($_POST["region_c_id"][0]))))
            { 
            
            if($useLimit)
                if(count($_POST['region_c_id']) > $selectionLimit) 
                    $_POST['region_c_id'] = array_slice ($_POST['region_c_id'], 0, $selectionLimit);

                foreach ($_POST['region_c_id'] as $i => $value){
                    if($value != '') $geoList[] = $value;
                }

                //$geoList = substr(trim($geoList), 0, -1);  //remove trailing comma
                //$tierValue = 3;
                $tierText = 'lga';

            } else if( isset($_POST["district_id"]) && 
                (count($_POST["district_id"])>1 || 
                (count($_POST["district_id"])==1 AND !empty($_POST["district_id"][0]))))
            {
                if($useLimit)
                    if(count($_POST['district_id']) > $selectionLimit) 
                        $_POST['district_id'] = array_slice ($_POST['district_id'], 0, $selectionLimit);

                foreach ($_POST['district_id'] as $i => $value){
                    if($value != '') $geoList[] = $value;
                }

                //$geoList = substr(trim($geoList), 0, -1);  //remove trailing comma
                //$tierValue = 2;
                $tierText = 'state';

            } else if( isset($_POST["province_id"]) && 
                (count($_POST["province_id"])>1 || 
                (count($_POST["province_id"])==1 AND !empty($_POST["province_id"][0]))))
            {
                if($useLimit)
                    if(count($_POST['province_id']) > $selectionLimit) 
                        $_POST['province_id'] = array_slice ($_POST['province_id'], 0, $selectionLimit);

                //$where .= 'AND flv.geo_parent_id IN (';
                foreach ($_POST['province_id'] as $i => $value){
                    if($value != '') $geoList[] = $value;
                }

                //$geoList = substr(trim($geoList), 0, -1);  //remove trailing comma
                //$tierValue = 1;
                $tierText = 'geozone';
            }
            else { //no geo selection
                $tierValue = 1;
                $tierText = 'geozone';
                $geoNamesArray = $helper->getLocationNamesByTierId($tierValue);
                foreach($geoNamesArray as $key=>$geoName)  //add quotes to each value
                    $geoList[] = $geoName;

                //var_dump($geoNamesArray); exit;
                //$geoList = implode(',', $geoNamesArray);
            }
            
            return array($geoList, $tierText);
      }
 
}