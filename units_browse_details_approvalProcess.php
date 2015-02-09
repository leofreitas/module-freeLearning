<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

include "../../functions.php" ;
include "../../config.php" ;

include "./moduleFunctions.php" ;

//New PDO DB connection
try {
  	$connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
	$connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
  echo $e->getMessage();
}

@session_start() ;

//Set timezone from session variable
date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL=$_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_GET["address"]) . "/units_browse_details.php&freeLearningUnitID=" . $_POST["freeLearningUnitID"] . "&freeLearningUnitStudentID=" . $_POST["freeLearningUnitStudentID"] . "&sidebar=true" ;

if (isActionAccessible($guid, $connection2, "/modules/Free Learning/units_browse_details_approval.php")==FALSE) {
	//Fail 0
	$URL.="&updateReturn=fail0" ;
	header("Location: {$URL}");
}
else {
	$highestAction=getHighestGroupedAction($guid, "/modules/Free Learning/units_browse_details_approval.php", $connection2) ;
	if ($highestAction==FALSE) {
		//Fail 0
		$URL.="&updateReturn=fail0" ;
		header("Location: {$URL}");
	}
	else {
		$freeLearningUnitID=$_POST["freeLearningUnitID"] ;
		$freeLearningUnitStudentID=$_POST["freeLearningUnitStudentID"] ;
	
		if ($freeLearningUnitID=="" OR $freeLearningUnitStudentID=="") {
			//Fail 3
			$URL.="&updateReturn=fail3" ;
			header("Location: {$URL}");
		}
		else {
			try {
				$data=array("freeLearningUnitID"=>$freeLearningUnitID, "freeLearningUnitStudentID"=>$freeLearningUnitStudentID); 
				$sql="SELECT * FROM freeLearningUnit JOIN freeLearningUnitStudent ON (freeLearningUnitStudent.freeLearningUnitID=freeLearningUnit.freeLearningUnitID) WHERE freeLearningUnitStudent.freeLearningUnitID=:freeLearningUnitID AND freeLearningUnitStudentID=:freeLearningUnitStudentID AND status='Complete - Pending'" ; 
				$result=$connection2->prepare($sql);
				$result->execute($data);
			}
			catch(PDOException $e) { 
				//Fail 2
				$URL.="&updateReturn=fail2" ;
				header("Location: {$URL}");
				break ;
			}
			
			if ($result->rowCount()!=1) {
				//Fail 2
				$URL.="&updateReturn=fail2" ;
				header("Location: {$URL}");
				break ;
			}
			else {
				//Proceed!
				$row=$result->fetch() ;
				
				$proceed=FALSE ;
				//Check to see if we can set enrolmentType to "staffEdit" if user has rights in relevant department(s)
				$learningAreas=getLearningAreas($connection2, $guid, TRUE) ;
				if ($learningAreas!="") {
					for ($i=0; $i<count($learningAreas); $i=$i+2) {
						if (is_numeric(strpos($row["gibbonDepartmentIDList"], $learningAreas[$i]))) {
							$proceed=TRUE ;
						}
					}
				}
				
				if ($proceed==FALSE) {
					//Fail 0
					$URL.="&updateReturn=fail0" ;
					header("Location: {$URL}");
				}
				else {
					//Get Inputs
					$status='Complete - Approved' ;
					$commentApproval=$_POST["commentApproval"] ;
					$gibbonPersonIDStudent=$row["gibbonPersonIDStudent"] ;
					
					//Validation
					if ($commentApproval=="") {
						//Fail 3
						$URL.="&updateReturn=fail3" ;
						header("Location: {$URL}");
					}
					else {
						//Write to database
						try {
							$data=array("status"=>$status, "commentApproval"=>$commentApproval, "gibbonPersonIDApproval"=>$_SESSION[$guid]["gibbonPersonID"], "timestampCompleteApproved"=>date("Y-m-d H:i:s"), "freeLearningUnitStudentID"=>$freeLearningUnitStudentID); 
							$sql="UPDATE freeLearningUnitStudent SET status=:status, commentApproval=:commentApproval, gibbonPersonIDApproval=:gibbonPersonIDApproval, timestampCompleteApproved=:timestampCompleteApproved WHERE freeLearningUnitStudentID=:freeLearningUnitStudentID" ;
							$result=$connection2->prepare($sql);
							$result->execute($data);
						}
						catch(PDOException $e) { 
							//Fail 2
							$URL.="&updateReturn=fail2" ;
							header("Location: {$URL}");
							exit ;
						}
					
						//Attempt to notify the student
						$text=_("A teacher has approved your request for unit completion.") ;
						$actionLink="/index.php?q=/modules/Free Learning/units_browse_details.php&freeLearningUnitID=$freeLearningUnitID&sidebar=true" ;
						setNotification($connection2, $guid, $gibbonPersonIDStudent, $text, "Free Learning", $actionLink) ;
					
						//Success 0
						$URL.="&updateReturn=success0" ;
						header("Location: {$URL}");
					}
				}
			}
		}
	}
}
?>