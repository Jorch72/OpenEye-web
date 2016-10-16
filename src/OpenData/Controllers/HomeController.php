<?php

namespace OpenData\Controllers;
use \Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HomeController {

    private $serviceMods;
    private $twig;

    public function __construct($twig, $mods) {
        $this->twig = $twig;
        $this->serviceMods = $mods;
    }

    public function home() {
        return $this->twig->render('home.twig', array(
            'mods' => $this->serviceMods->findForHomepage(),
            'title' => 'Most popular',
            'tags' => $this->serviceMods->getDistinctTags()
        ));
    }

    public function download() {
        return $this->twig->render('download.twig');
    }

    public function get_file(Application $app, Request $request) {
        $mc_version = $request->get('mc_version');
        $mod_version = $request->get('mod_version');
        if ($mc_version === null || $mod_version === null) {
            throw new NotFoundHttpException('Invalid file');
        }
        $now = date("Y-m-d");
        $redis = new \Predis\Client();
        $redis->hincrby("downloads:total", "$mod_version:$mc_version", 1);
        $redis->hincrby("downloads:{$now}", "$mod_version:$mc_version", 1);
        $path = "/releases/{$mod_version}/OpenEye-{$mc_version}-{$mod_version}.jar";
        return $app->redirect($path);
    }

    public function storagepolicy() {
        return $this->twig->render('storagepolicy.twig');
    }

    public function faq() {
        return $this->twig->render('faq.twig');
    }

    public function stats() {
        return $this->twig->render('stats.twig');
    }

    public function configuration() {
        return $this->twig->render('configuration.twig');
    }

    public function all(Request $request) {
        return $this->twig->render('home.twig', array_merge(
                $this->getPagination(
                    $this->serviceMods->findAll(),
                    $request->get('page', 1),
                    100
                ),
                array(
                    'title' => 'Listing all mods'
                )
        ));
    }

    public function letter(Request $request, $letter) {

        if ($letter == 'others') {
            $title = 'Other mods';
        } else if (strlen($letter) == 1) {
            $title = 'Mods beginning with "'.strtoupper($letter).'"';
        } else {
            throw new NotFoundHttpException('Not a letter');
        }

        return $this->twig->render('home.twig', array_merge(
                $this->getPagination(
                    $this->serviceMods->findByLetter($letter),
                    $request->get('page', 1)
                ),
                array(
                    'title' => $title
                )
        ));
    }

    public function tag(Request $request, $tag) {

        $result = $this->serviceMods->findByTag($tag);

        if ($result->count() == 0) {
            throw new NotFoundHttpException('Invalid tag name');
        }

        return $this->twig->render('home.twig', array_merge(
                $this->getPagination(
                    $result,
                    $request->get('page', 1)
                ),
                array(
                    'title' => 'Mods tagged with "'.$tag.'"'
                )
        ));
    }

    private function getPagination($iterator, $page = 1, $perPage = 20) {

        $skip = ($page - 1) * $perPage;
        $total = $iterator->count();

        $pageCount = max(1, ((int) ($total - 1) / $perPage) + 1);

        if ($page > $pageCount || $page < 1) {
            throw new NotFoundHttpException('Invalid page number');
        }

        return array(
            'mods' => $iterator->skip($skip)->limit($perPage),
            'page_count' => $pageCount,
            'current_page' => $page,
            'total' => $total,
            'disablePrev' => $page <= 1,
            'disableNext' => $page + 1 >= $pageCount,
            'tags' => $this->serviceMods->getDistinctTags()
        );

    }

}
