<?php


namespace OpenData\PacketHandlers;

class KnownFiles extends SignaturesBase {

    public function __construct($files) {
        parent::__construct($files);
    }

    public function getPacketType() {
        return 'known_files';
    }

    public function getJsonSchema() {
        return 'known_files.json';
    }
}
