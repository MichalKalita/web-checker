<?php

namespace Model;

use Nette,
    SplPriorityQueue;

/**
 * Description of Model
 *
 * @author Michal Kalita
 */
class Downloader extends Nette\Object {

    /** @var array */
    public $onLoad;

    /** @var curl_multi */
    private $multiload;

    /** @var integer */
    private $limit = 1;

    /** @var integer */
    private $timeout = 10;

    /** @var SplPriorityQueue */
    private $queue;

    /** @var array */
    private $running;

    /** @var array */
    private $times;

    /** @var array */
    private $urls;

    /** @var boolean */
    private $stop = FALSE;

    public function __construct() {
	$this->queue = new SplPriorityQueue();
    }

    public function setLimit($limit) {
	$this->limit = $limit;
    }

    public function addUrl($id, $url) {
	$this->queue->insert(array($id, $url), rand(0, 999));
    }

    public function run() {
	$this->multiload = curl_multi_init();
	$active = null;

	while (TRUE) {
	    $this->testMemoryLimit();
	    while (count($this->running) < $this->limit && $this->queue->count() > 0 && !$this->stop) {
		list($id, $url) = $this->queue->current();
		$this->queue->next();
		$this->runUrl($id, $url);
	    }
	    if (count($this->running) == 0) {
		break;
	    }

	    curl_multi_exec($this->multiload, $active);
	    usleep(10);
//	    $info = curl_multi_info_read($this->multiload);

	    $this->checkLoading();
	}

	curl_multi_close($this->multiload);
    }

    private function runUrl($id, $url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout); // timeout
	curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_HEADER, 1);

	curl_multi_add_handle($this->multiload, $ch);
	$this->urls[$id] = $url;
	$this->running[$id] = $ch;
    }

    private function cleanUrl($id) {
	curl_close($this->running[$id]);
	curl_multi_remove_handle($this->multiload, $this->running[$id]);
	/*
	  unset($this->running[$id]);
	 */

	unset($this->urls[$id], $this->running[$id], $this->times[$id]);
    }

    private function checkLoading() {
	foreach ($this->running as $id => $ch) {
	    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
	    if (isset($this->times[$id]) && $total_time == $this->times[$id]) {
		$header = $body = NULL;
		$status = FALSE;
		$response = curl_multi_getcontent($ch);

		if ($response !== NULL) {
		    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		    $header = substr($response, 0, $header_size);
		    $body = substr($response, $header_size);
		}

		$this->onLoad($id, $this->urls[$id], $header, $body, $status);
		$this->cleanUrl($id);
	    } else {
		$this->times[$id] = $total_time;
	    }
	}
    }

    private function testMemoryLimit() {
	$limit = 256 * 1024 * 1024;

	if (memory_get_peak_usage() > $limit) {
	    echo "\n\033[1m\033[31m!!!! FATAL MEMORY LIMIT !!!!\033[0m\n";
	    exit(2);
	} elseif (memory_get_peak_usage() > ($limit * 0.85)) {
	    echo "\n\033[1m\033[31m!!!! MEMORY LIMIT !!!!\033[0m\n";
	    $this->stop = TRUE;
	    return TRUE;
	} else {
	    return FALSE;
	}
    }

}
