<?php

namespace OpenData\Controllers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ModController {

    private $serviceFiles;
    private $serviceMods;
    private $twig;
    private $app;

    public function __construct($twig, $app, $files, $mods) {
        $this->app = $app;
        $this->twig = $twig;
        $this->serviceFiles = $files;
        $this->serviceMods = $mods;
    }

    public function find(Request $request) {

        $query = $request->get('q');
        if ($query == null) {
            return new JsonResponse(array());
        }

        $modNames = array();

        $results = $this->serviceMods->findByRegex($request->get('q'), false);
        $results->sort(array(
            'name' => 1
        ));

        foreach ($results as $mod) {
            $modNames[] = $mod['name'];
        }

        return new JsonResponse($modNames);
    }

    private function extractSeenMc(&$stats) {
        $seenMc = array();

        foreach ($stats as  $k => $v) {
            if (substr($k, 0, 7) == "seenMc:") {
                $mc = substr($k, 7);
                $seenMc[$mc] = $v;
                unset($stats[$k]);
            }
        }
        arsort($seenMc);
        return $seenMc;
    }

    public function modinfo($modId, $versionFilter="latest") {

        $modInfo = $this->serviceMods->findById($modId);

        if ($modInfo == null) {
            $this->app->abort(404, "Modid not found");
        }

        if (isset($modInfo['unlisted']) && $modInfo['unlisted'] === true) {
            $this->app->abort(403, "Mod hidden per author request");
        }

        $files = $this->serviceFiles->findByModId($modId);

        $numFiles = $files->count();

        if ($numFiles == 0) {
            $this->app->abort(404, "Modid not found");
        }

        $redis = new \Predis\Client();

        $versions = array();

        foreach ($files as $file) {
            $stats = $redis->hgetall("file_stats:" . $file['_id']);
            $seenMc = $this->extractSeenMc($stats);
            $file['stats'] = $stats;
            $file['seenMc'] = $seenMc;

            foreach ($file['mods'] as $mod) {
                if ($mod['modId'] == $modId) {
                    $version = $mod['version'];
                    if (!isset($versions[$version])) {
                        $versions[$version] = array();
                    }
                    $versions[$version][] = $file;
                }
            }
        }

        uksort($versions, 'version_compare');
        $versions = array_reverse($versions, true);

        $versionGroup = "(^[0-9]+\.[0-9]+\.[0-9]+)(.*)";
        if (isset($modInfo['versionGroup'])) {
            $versionGroup = $modInfo['versionGroup'];
        }

        $groupedVersions = array();
        foreach ($versions as $version => $files) {
            $version = preg_replace("@".$versionGroup."@", "$1", $version);
            if (!isset($groupedVersions[$version])) {
                $groupedVersions[$version] = array();
            }
            $groupedVersions[$version] = array_merge($groupedVersions[$version], $files);
        }

        if ($versionFilter === "latest") {
            $versionValue = reset($groupedVersions);
            $versionKey = key($groupedVersions);
            $filteredVersions = array($versionKey => $versionValue);
        } elseif ($versionFilter === "all") {
            $filteredVersions = $groupedVersions;
        } else {
            if (isset($groupedVersions[$versionFilter])) {
                $filteredVersions = array($versionFilter => $groupedVersions[$versionFilter]);
            } else {
                $filteredVersions = array();
            }
        }

        return $this->twig->render('mod.twig', array(
            'versionFilter' => $versionFilter,
            'allVersions' => array_keys($groupedVersions),
            'versions' => $filteredVersions,
            'modInfo' => $modInfo
        ));
    }

    public function fileinfo($fileId) {

        $file = $this->serviceFiles->findOne($fileId);

        if ($file == null) {
            $this->app->abort(404, "File not found");
        }

        $redis = new \Predis\Client();
        $file['stats'] = $redis->hgetall('file_stats:' . $fileId);

        $stats = $redis->hgetall("file_stats:" . $file['_id']);
        $seenMc = $this->extractSeenMc($stats);
        $file['stats'] = $stats;
        $file['seenMc'] = $seenMc;
        return $this->twig->render('file.twig', array(
            'file' => $file
        ));
    }

    public function crashes($modId, $fileId = null) {
        $signatures = array();
        if ($fileId == null) {
            foreach($this->serviceFiles->findByModId($modId) as $file) {
                $signatures[] = $file['_id'];
            }
        } else {
            $signatures[] = $fileId;
        }

        $crashes = $this->serviceCrashes->findUniqueBySignatures($signatures);

        return $this->twig->render('mod_crashes.twig', array(
            'crashes' => $crashes
        ));
    }
}
