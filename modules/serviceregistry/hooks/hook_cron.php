<?php
ini_set('display_errors', true);
require __DIR__ . '/../www/_includes.php';
require __DIR__ . '/../lib/ServiceRegistry/Cron/Job/MetadataRefresh.php';
require __DIR__ . '/../lib/ServiceRegistry/Cron/Logger.php';
require __DIR__ . '/../lib/ServiceRegistry/Cron/Job/ValidateEntityCertificate.php';
require __DIR__ . '/../lib/ServiceRegistry/Cron/Job/ValidateEntityEndpoints.php';

/**
 * Cron hook for SURFconext Service Registry
 *
 * This hook downloads the metadata of the entities registered in JANUS and
 * update the entities with the new metadata.
 *
 * @param array &$cronInfo The array with the tags and output summary of the cron run
 *
 * @return void
 *
 * @since Function available since Release 1.4.0
 */
function serviceregistry_hook_cron(&$cronInfo) {
    assert('is_array($cronInfo)');
    assert('array_key_exists("summary", $cronInfo)');
    assert('array_key_exists("tag", $cronInfo)');

    SimpleSAML_Logger::info('cron [janus]: Running cron in cron tag [' . $cronInfo['tag'] . '] ');

    // Refresh metadata
    $refresher = new ServiceRegistry_Cron_Job_MetadataRefresh();
    $summaryLines = $refresher->runForCronTag($cronInfo['tag']);
    $cronInfo['summary'] = array_merge($cronInfo['summary'], $summaryLines);

    // Validate entity signing certificates
    $validator = new ServiceRegistry_Cron_Job_ValidateEntityCertificate();
    $summaryLines = $validator->runForCronTag($cronInfo['tag']);
    $cronInfo['summary'] = array_merge($cronInfo['summary'], $summaryLines);

    // Validate entity endpoints
    $validator = new ServiceRegistry_Cron_Job_ValidateEntityEndpoints();
    $summaryLines = $validator->runForCronTag($cronInfo['tag']);
    $cronInfo['summary'] = array_merge($cronInfo['summary'], $summaryLines);
}