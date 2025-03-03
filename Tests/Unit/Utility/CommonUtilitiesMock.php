<?php
namespace Univie\UniviePure\Tests\Unit\Utility;

class CommonUtilitiesMock {
    public static function getBackendLanguage(): string {
        return 'en';
    }

    public static function getProjectsForDatasetsXml($settings) {
        return '<uuids><uuid>mock-project-uuid</uuid></uuids>';
    }

    public static function getPageSize($pageSize) {
        if ($pageSize == 0 || $pageSize === null) {
            $pageSize = 20;
        }
        return '<size>' . $pageSize . '</size>';
    }

    public static function getOffset($pageSize, $currentPage) {
        $offset = $currentPage;
        $offset = ($offset - 1 < 0) ? 0 : $offset - 1;
        return '<offset>' . $offset * (int)$pageSize . '</offset>';
    }

    public static function getPersonsOrOrganisationsXml($settings) {
        return '<forOrganisationalUnits><uuids><uuid>mock-org-uuid</uuid></uuids></forOrganisationalUnits>';
    }

    public static function getProjectsXml($settings) {
        return '<uuids><uuid>mock-project-uuid</uuid></uuids>';
    }
}