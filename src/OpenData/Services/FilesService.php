<?php

namespace OpenData\Services;

class FilesService extends BaseService {

    public function findIn($signatures = array()) {
        return $this->db->files->find(
                        array('_id' => array('$in' => $signatures))
        );
    }

    public function create($signature) {
        try {
            $this->db->files->insert(array(
                '_id' => $signature['signature'],
                'filenames' => array($signature['filename'])
            ));
        } catch (\MongoCursorException $e) {
            return false;
        }
        return true;
    }

    public function findByModId($modId) {
        return $this->db->files->find(
            array('mods.modId' => strtolower($modId))
        );
    }

    public function findByPackage($package) {
        return $this->db->files->find(
            array('packages' => $package)
        );
    }

    public function findSubPackages($package) {

        $results = $this->db->files->aggregate(
            array('$project' => array('packages' => 1)),
            array('$unwind' => '$packages'),
            array('$match' => array('packages' => new \MongoRegex('/^'.preg_quote($package).'\./'))),
            array('$group' => array('_id' => '$packages')));

        $packages = array();
        foreach ($results['result'] as $result) {
            $packages[] = $result['_id'];
        }

        return $packages;
    }

    public function hasPackage($package) {
        return $this->findByPackage($package)->count() > 0;
    }

    public function findUniqueModIdsForPackage($package) {

        $results = $this->db->files->aggregate(
                array('$match' => array('packages' => $package)),
                array('$project' => array('mods' => 1)),
                array('$unwind' => '$mods'),
                array('$group' => array('_id' => '$mods.modId'))
                );

        $mods = array();
        foreach ($results['result'] as $result) {
            $mods[] = $result['_id'];
        }

        return $mods;
    }

    public function append($file, $overwrite = false) {

        $signature = $file['signature'];

        $currentEntry = $this->findOne($signature);

        if ($currentEntry == null) {
            return false;
        }

        unset($file['signature']);

        foreach ($file as $k => $v) {
            if ($overwrite || (!$overwrite && !isset($currentEntry[$k]))) {
                $currentEntry[$k] = $v;
            }
        }

        if (isset($file['mods'])) {
            for ($i = 0; $i < count($file['mods']); $i++) {
                    $file['mods'][$i]['modId'] = strtolower($file['mods'][$i]['modId']);
            }
        }

        unset($currentEntry['_id']);

        $this->db->files->update(
                array('_id' => $signature), array('$set' => $currentEntry)
        );

        return true;
    }

    public function findOne($signature) {
        return $this->db->files->findOne(array('_id' => $signature));
    }

    public function findUniquePackages() {
        return $this->db->files->distinct('packages');
    }

    public function findDownloadsForSignatures($signatures) {
        if (count($signatures) == 0) {
            return array();
        }
        $downloads = array();
        foreach ($this->db->urls->find(
                array('_id' => array('$in' => $signatures))
        ) as $download) {
            $downloads[$download['_id']] = $download;
        }
        return $downloads;
    }

}
