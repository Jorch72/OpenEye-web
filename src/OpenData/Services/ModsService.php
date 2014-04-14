<?php

namespace OpenData\Services;

class ModsService extends BaseService {

    public function findById($modId) {
        return $this->db->mods->findOne(array('_id' => $modId));
    }

    public function findAll() {
        return $this->db->mods->find(array('hide' => array('$ne' => true)))->sort(array('name' => 1));
    }

    public function findByIds($modIds = array()) {
        return $this->db->mods->find(array('_id' => array('$in' => $modIds)));
    }

    public function upsert($modId, $data) {
        return $this->db->mods->findAndModify(
            array('_id' => $modId),
            array('$setOnInsert' => $data),
            null,
            array('new' => true, 'upsert' => true)
        );
    }
    
    public function getDistinctTags() {
        return $this->db->mods->distinct('tags');
    }
    
    public function findByLetter($letter) {
        $letter = substr($letter, 0, 1);
        return $this->db->mods->find(array(
            'hide' => array('$ne' => true),
            'name' => new \MongoRegex('/^'.$letter.'/i')
        ))->sort(array('name' => 1));
    }
    
    public function findByTag($tag) {
        $tag = trim($tag);
        return $this->db->mods->find(array(
            'hide' => array('$ne' => true),
            'tags' => new \MongoRegex('/^'.$tag.'/i')
        ))->sort(array('name' => 1));
    }

    public function findOrderedByPastHourLaunches($limit = 50, $filterLibraries = true) {
    	return $this->findAll();
    /*
        $currentHour = strtotime(date("Y-m-d H:00:00"));
        $searchDate = new \MongoDate($currentHour);
        return $this->db->mods->find(
            array(
                //'hours.time' => $searchDate,
                'tags' => array('$ne' => 'library'),
                'hide' => array('$ne' => true),
                'image' => array('$exists' => 1)
            ),
            array(
                'name' => 1,
                'authors' => 1,
                'description' => 1,
                'tags' => 1,
                'image' => 1,
                'hours' => array('$elemMatch' => array('time' => $searchDate))
            )
        )->sort(array('hours.launches' => -1))->limit($limit);*/
    }

}
