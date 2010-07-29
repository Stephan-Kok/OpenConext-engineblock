<?php

ini_set('display_errors', true);

define('CORTO_APPLICATION_OVERRIDES_DIRECTORY', realpath(dirname(__FILE__).'/../').'/');
require './../corto/www/corto.php';

function engineBlockSetupMetadata()
{
    $GLOBALS['metabase']['remote'] = array();

    $metaDataXml = file_get_contents(ENGINEBLOCK_SERVICEREGISTRY_METADATA_URL);
    $hash = Corto_XmlToHash::xml2hash($metaDataXml);

    foreach ($hash['md:EntityDescriptor'] as $entity) {
        $entityID = $entity['_entityID'];
        $metaData = array();
        if (isset($entity['md:IDPSSODescriptor']['md:SingleSignOnService'])) {
            $metaData['SingleSignOnService'] = array(
                'Binding' =>$entity['md:IDPSSODescriptor']['md:SingleSignOnService']['_Binding'],
                'Location'=>$entity['md:IDPSSODescriptor']['md:SingleSignOnService']['_Location'],
            );
        }
        if (isset($entity['md:SPSSODescriptor']['md:AssertionConsumerService'])) {
            $metaData['AssertionConsumerService'] = array(
                'Binding' =>$entity['md:SPSSODescriptor']['md:AssertionConsumerService']['_Binding'],
                'Location'=>$entity['md:SPSSODescriptor']['md:AssertionConsumerService']['_Location'],
            );

            $metaData['WantResponsesSigned']  = true;
            $metaData['WantAssertionsSigned'] = true;
        }

        if (isset($entity['md:Organization'])) {
            if (isset($entity['md:Organization']['md:OrganizationDisplayName'])) {
                $metaData['OrganizationDisplayName'] = $entity['md:Organization']['md:OrganizationDisplayName']['__v'];
            }
            else {
                $metaData['OrganizationDisplayName'] = $entity['md:Organization']['md:OrganizationName']['__v'];
            }
        }


        $GLOBALS['metabase']['remote'][$entityID] = $metaData;
    }
}

function engineBlockOutfilter(array $metaData, array $response, array &$attributes)
{
    engineBlockEnrichAttributes($attributes);
    engineBlockRegisterUser($attributes);
}

function engineBlockEnrichAttributes(array &$attributes)
{
    $attributes['over-18'] = array('yup');
    $attributes['coin-team-member'] = array('yes');
}

function engineBlockRegisterUser($attributes)
{
    if (!defined('ENGINEBLOCK_USER_DB_DSN') && ENGINEBLOCK_USER_DB_DSN) {
        return false;
    }

    $uid = $attributes['uid'][0];

    $dbh = new PDO(ENGINEBLOCK_USER_DB_DSN, ENGINEBLOCK_USER_DB_USER, ENGINEBLOCK_USER_DB_PASSWORD);
    $statement = $dbh->prepare("INSERT INTO `users` (uid, last_seen) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_seen = NOW()");
    $statement->execute(array($uid));

    $sqlValues = array();
    $bindValues = array('uid'=>$uid);

    $nameCount = 1;
    $valueCount = 1;
    foreach ($attributes as $attributeName => $attributeValues) {
        if ($attributeName==='uid') {
            continue;
        }

        $bindValues['attributename' . $nameCount] = $attributeName;

        foreach ($attributeValues as $attributeValue) {
            $sqlValues[] = "(:uid, :attributename{$nameCount}, :attributevalue{$valueCount})";
            $bindValues['attributevalue' . $valueCount] = $attributeValue;
            $valueCount++;
        }
        $nameCount++;
    }
    
    // No other attributes than uid found
    if (empty($sqlValues)) {
        return false;
    }

    $statement = $dbh->prepare("INSERT IGNORE INTO `user_attributes` (`user_uid`, `name`, `value`) VALUES " . implode(',', $sqlValues));
    $statement->execute($bindValues);
}

function engineBlockTranslateAttributeName($name)
{
    if (isset($GLOBALS['attribute_names']['nl_NL'][$name])) {
        return $GLOBALS['attribute_names']['nl_NL'][$name];
    }

    if (isset($GLOBALS['attribute_names']['nl_NL']['urn:mace:dir:attribute-def:' . $name])) {
        return $GLOBALS['attribute_names']['nl_NL']['urn:mace:dir:attribute-def:' . $name];
    }

    return $name;
}