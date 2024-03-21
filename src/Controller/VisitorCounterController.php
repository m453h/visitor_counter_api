<?php

namespace Drupal\visitor_counter_api\Controller;
use Drupal;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Request;

/**
 * VisitorCounter controller class.
 */
class VisitorCounterController {

  /**
   * @throws \Exception
   */
  public function index(): JsonResponse {
    return new JsonResponse($this->processUserRequest());
  }

  /**
   *
   * @throws \Exception
   */
  public function processUserRequest(): array {

    // Get the current request object.
    $request = \Drupal::request();

    // Check if the request method is POST.
    if ($request->isMethod('POST')) {
      $data = $request->getContent();
      $currentDateTime = date('Y-m-d H:i:s');
      $timeThreshold = date('Y-m-d H:i:s', strtotime('-10 minutes', strtotime($currentDateTime)));
      $data = json_decode($data, true);
      $data['ip_address'] = \Drupal::request()->getClientIp();
      $data['first_visit_time'] = $currentDateTime;
      $data['last_visit_time'] = $currentDateTime;
      $data['is_complete'] = 0;

      $results = \Drupal::database()->select('visitor_counter_logs', 'vcl')
        ->fields('vcl')
        ->condition('vcl.client_fingerprint', $data['client_fingerprint'])
        ->condition('vcl.ip_address', $data['ip_address'])
        ->condition('vcl.last_visit_time', $timeThreshold, '>=')
        ->orderBy('id','DESC')
        ->range(0, 1)
        ->execute()
        ->fetch();

      if (empty($results)) {
        \Drupal::database()->insert('visitor_counter_logs')
          ->fields($data)
          ->execute();
      }

    }

    return [
      'online'=>$this->getCurrentOnlineVisitors(),
      'today'=>$this->getTodayVisitors(),
      'yesterday'=>$this->getYesterdayVisitors(),
      'week'=>$this->getThisWeekVisitors(),
      'overall'=>$this->getOverallVisitors()
    ];
  }

  public function getCurrentOnlineVisitors(): string {
    $result = Drupal::database()->query(
      'SELECT COUNT(DISTINCT(client_fingerprint)) as total
              FROM visitor_counter_logs vcl
              WHERE vcl.last_visit_time >= NOW() - INTERVAL 10 MINUTE')
      ->fetchAssoc();
    return number_format($result['total']);
  }

  public function getTodayVisitors(): string {
    $result = Drupal::database()->query(
      'SELECT COUNT(DISTINCT(client_fingerprint)) as total
              FROM visitor_counter_logs vcl
              WHERE DATE(vcl.last_visit_time) = CURRENT_DATE();')
      ->fetchAssoc();
    return number_format($result['total']);
  }

  public function getYesterdayVisitors(): string {
    $result = Drupal::database()->query(
      'SELECT COUNT(DISTINCT(client_fingerprint)) as total
              FROM visitor_counter_logs vcl
              WHERE DATE(vcl.last_visit_time) = DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY);')
      ->fetchAssoc();
    return number_format($result['total']);
  }

  public function getThisWeekVisitors(): string {
    $result = Drupal::database()->query(
      'SELECT COUNT(DISTINCT(client_fingerprint)) as total
              FROM visitor_counter_logs vcl
              WHERE YEARWEEK(vcl.last_visit_time) = YEARWEEK(CURRENT_DATE());')
      ->fetchAssoc();
    return number_format($result['total']);
  }

  public function getOverallVisitors(): string {
    $result = Drupal::database()->query(
      "SELECT DATE_FORMAT(MIN(first_visit_time), '%e %M, %Y') as first_day,
                COUNT(DISTINCT(client_fingerprint)) as total
              FROM visitor_counter_logs vcl;")
      ->fetchAssoc();

    return sprintf("%s (since %s)",
      $result['total'],
      $result['first_day']);
  }
}
