<?php

namespace Drupal\ek_projects\Service;

/**
 * Interface for ProjectService.
 */
interface ProjectServiceInterface {

 /**
  * Generate internal/external link to the project,
  * a project code view url from id or serial
  * @param mix $id
  *  project id or project serial code
  * @param bolean $abs
  *  true|false absolute url 
  * @param bolean $dest
  *  true|false create link as destination
  * @param bolean $short
  *  true|false return only last part of serial code (number)
  * @param string $text
  *  custom text for link
  * @param array $param
  *  query string option
  * @param array $fragment
  *  fragment string option
  *
  * @return string 
  *  element link or empty string
 */
  public function geturl($id, $abs = null, $dest = null, $short = null, $text = null, $param = null, $fragment = null);
  
 /**
  * File managed data
  * @param int  $uri
  *   file uri
  * @return object
  *   the file object
  */
  public function file_owner($uri);
  
 /**
  * A formated list
  * @param array
  *  param
  * @return
  *  array of formated project list for select field
  *  pcode => description
  *
  */

  public function format_project_list($param);
 
 /**
  * Projects list
  * @param string
  *  arvhive
  * @return array
  *  projects by user access - country / company
  *  classified projects per staus and return array $key => $description
  *
 */

  public function listprojects($archive = '%');

 /**
  * Validation access by user id and project id
  * @param int  $id
  *   project id
  * @param int $uid  
  *   user id provided if not current user to be checked
  * @return bolean
  *   true or false
  */
  public function validate_access($id, $uid = null);
  
  
 /**
  * Validation access by file from user
  * @param int  $id
  *   file id
  * @return bolean 
  *   true or false
  */
  public function validate_file_access($id);
    
  
 /**
  * Validation access by section from user
  * @param int  $uid
  *   user id
  * @param int $uid  
  *   user id provided if not current user to be checked
  * @return
  *   array of sections
  */
  public function validate_section_access($uid);
  
  
 /**
  * The sections name from settings
  *  @return array
  */
  public function sectionsName();
  
  
 /**
  * Calculate ratio of filled data per project
  * @param $id
  *   project id
  *  @return double
  */
  public function data_fill($id);
  
 /**
  * List of followers per project
  * @param $id
  *   project id  
  *  @return string  html list
  */  
  public function followers($id);
  
 /**
  * Notification service to users
  * @param $param
  *  serialize data
  *  id = project id, field = field edited, value = new value, 
  *  @return object response
  */
  public function notify_user($param);
  
 /**
  * Return project id
  * @param string $pcode
  *  @return int id
  */
  public function getId($pcode);
  
 /**
  * Calculate ratio of filled data per project
  * @param string $pcode
  *   project code
  * @param $status
  *   project status, get or change if status != $status
  *  @return string
  */
  public function status($pcode, $status = null);

/**
 * Get projects by owner.
 *
 
 * @param array $options
 *   Optional parameters:
 *   - include_archived: Include archived projects (default: FALSE)
 *   - status_filter: open|awarded|completed|closed (default: NULL)
 *   - owner_id (default: NULL)
 *   - country (default: NULL)
 *   - project_code (default: NULL)
 *   - since: start from date
 *
 * @return array
 *   Array of project data with pcode and status.
 *
 * @throws \InvalidArgumentException
 *   If owner_id is not valid.
 */
  public function getProjects(array $options = []);


/**
 * Get project description by project code.
 *
 * @param string $project_code
 *   The project code.
 *
 * @return array
 *   Description text.
 */
  public function getProjectDescription($project_code);


/**
 * Get project documents by project code.
 *
 * @param string $project_code
 *   The project code.
 *
 * @return array
 *   Attached document(s) list.
 */
  public function getProjectDocument($project_code);

/**
 * Get project finance data by project code.
 *
 * @param string $project_code
 *   The project code.
 *
 * @return array
 *   Array of project data.
 */
  public function getProjectFinance($project_code);

/**
 * Download a project document by document ID.
 *
 * @param int $document_id
 *   The document ID.
 *
 * @return array
 *   Array with 'success' boolean and 'file' or 'error' key.
 */
  public function downloadProjectDocument($document_id);

/**
 * Upload a project document.
 *
 * @param string $project_code
 *   The project code.
 * @param array $file_data
 *   The uploaded file data with 'name', 'tmp_name', 'size', 'type'.
 * @param string $folder
 *   The folder type (fi for finance, com for commercial).
 * @param string|null $sub_folder
 *   Optional sub folder or tag.
 * @param string|null $comment
 *   Optional comment.
 *
 * @return array
 *   Result array with success status, document ID, or errors.
 */
  public function uploadProjectDocument($project_code, $file_data, $folder, $sub_folder = null, $comment = null);

}  
  
  
  
  
  
  
  
  
  
  
  
  
  