<?php

use Composer\Semver\Comparator;
use Composer\Semver\Semver;

/**
 * This file does the following things:
 * 1. Calculate how many major versions back the current installation
 *    is from the latest release
 * 2. Returns the matching icon based on result of 1, as HTML
 * 3. Returns the matching tooltip message based on result of 1, as HTML
 *    (stored in pair of invisible div tags)
 * 4. Returns the current version, as HTML (stored in pair of invisible div tags)
 */
class Airtime_View_Helper_VersionNotify extends Zend_View_Helper_Abstract {
    
    public function versionNotify()
    {
        $config = Config::getConfig();

        // retrieve and validate current and latest versions,
        $current = $config['airtime_version'];
        $latest = Application_Model_Preference::GetLatestVersion();
        $link = Application_Model_Preference::GetLatestLink();

        $isGitRelease = preg_match('/^[[:alnum:]]{7,}$/i', $current) === 1;
        $currentParts = array();
        if (!$isGitRelease) {
            $currentParts = preg_split("/(\.|-)/", $current);
        }
        if (count($currentParts) < 3) {
            $currentParts = array(0, 0, 0, 0);
        }

        $isPreRelease = $isGitRelease || array_key_exists(4, $currentParts);
        // we are always interested in a major when we pre-release, hence the isPreRelease part
        $majorCandidates = SemVer::satisfiedBy($latest, sprintf('>=%1$s-stable', $currentParts[0] + ($isPreRelease ? 0 : 1)));
        $minorCandidates = SemVer::satisfiedBy($latest, sprintf('~%1$s.%2$s', $currentParts[0], $currentParts[1] + 1));
        $patchCandidates = SemVer::satisfiedBy($latest, sprintf('>=%1$s.%2$s.%3$s <%1$s.%3$s', $currentParts[0], $currentParts[1], $currentParts[2] + 1));
        $hasMajor = !empty($majorCandidates);
        $hasMinor = !empty($minorCandidates);
        $hasPatch = !empty($patchCandidates);
        $hasMultiMajor = count($majorCandidates) > 1;

        if ($isPreRelease) {
            $stableVersions = SemVer::satisfiedBy($latest, sprintf('>=%1$s.%2$s.%3$s-stable', $currentParts[0], $currentParts[1], $currentParts[2]));
            // git releases are never interested in a stable version :P
            $hasStable = !empty($stableVersions) && !$isGitRelease;
            // no warning if no major release available, orange warning if you are on unreleased code
            $class = $hasStable ? 'update2' : 'uptodate';
        } else if ($hasPatch || $hasMultiMajor) {
            // current patch or more than 1 major behind
            $class = 'outdated';
        } else if ($hasMinor) {
            // green warning for feature update
            $class = 'update';
        } else if ($hasMajor) {
            // orange warning for 1 major beind
            $class = 'update2';
        } else {
            $class = 'uptodate';
        }
        $latest = SemVer::rsort($latest);
        $highestVersion = $latest[0];

        $data = (object) array(
            'link' => $link,
            'latest' => $highestVersion,
            'current' => $current,
            'hasPatch' => $hasPatch,
            'hasMinor' => $hasMinor,
            'hasMajor' => $hasMajor,
            'isPreRelease' => $isPreRelease,
            'hasMultiMajor' => $hasMultiMajor,
        );

        $result = sprintf('<script>var versionNotifyInfo = %s;</script>', json_encode($data))
                . "<div id='version-icon' class='" . $class . "'></div>";
        return $result;
    }
}
