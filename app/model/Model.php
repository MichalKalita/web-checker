<?php

namespace Model;

use Nette,
    Nette\Database\SqlLiteral,
    Model\Downloader;

/**
 * Description of Model
 *
 * @author Michal Kalita
 */
class Model extends Nette\Object {

    /** @var Nette\Database\SelectionFactory */
    private $database;

    /** @var Downloader */
    private $loader;

    /** @var long */
    private $start_time;

    /** @var integer */
    private $loaded = 1;

    /** @var integer */
    private $limit;

    public function __construct(Nette\Database\SelectionFactory $database, Downloader $loader) {
	$this->database = $database;
	$this->loader = $loader;
    }

    public function check($limit, $dm, $sdm) {
	$this->start_time = time();

	\Nette\Diagnostics\Debugger::timer('page');

	$pages = $this->getPages($limit, NULL, NULL, $dm, $sdm);

	if (count($pages) == 0) {
	    echo "Nelze provést, nedostatek dat.\n";
	    exit(1);
	}
	$this->limit = count($pages);
	printf("%2.1f MB: ", memory_get_usage() / (1024 * 1024));

	foreach ($pages as $p) {
	    $url = $p->protocol . "://" . ($p->subd ? $p->subd . "." : "") . $p->domain;
	    $this->loader->addUrl($p->id, $url);
	}

	$this->loader->onLoad[] = $this->processPage;

	$this->loader->setLimit(10);
	$this->loader->run();
    }

    public function processPage($id, $url, $header, $body, $status) {
	if ($status === FALSE) {
	    $status = -1;
	    $usedNette = NULL;
	    $usedNetteText = "\033[1m\033[31mFAILED\033[0m";
	} else {
	    $urls = $this->matchPages($body);
	    foreach ($urls as $u) {
		$this->addLink($id, $u);
	    }

	    $usedNette = FALSE;
	    foreach (explode("\r\n", $header) as $h) {
		if ($h == "X-Powered-By: Nette Framework") {
		    $usedNette = TRUE;
		    break;
		}
	    }
	    $usedNetteText = $usedNette ? "\033[1m\033[32mY\033[0m" : "\033[1m\033[31mN\033[0m";
	    $usedNetteText .= " | \033[1m\033[32m" . count($urls) . "\033[0m links";
	}
	$this->updatePage($id, $usedNette, (int) $status);

	$timer = time() - $this->start_time;

	printf("%ds | %d/%d | %1s | %s\n", $timer, $this->loaded++, $this->limit, $usedNetteText, $url);

	printf("%2.1f MB: ", memory_get_usage() / (1024 * 1024));
    }

    private function addPage($page) {
	try {
	    return $this->database->table('page')->insert(array(
			'protocol' => $page['p'],
			'subd' => $page['s'],
			'domain' => $page['d'],
			'added' => new SqlLiteral('now()'),
	    ));
	} catch (\PDOException $e) {
	    if ($e->getCode() == 23000) {
		return FALSE;
	    } else {
		throw $e;
	    }
	}
    }

    private function updatePage($id, $nette, $status) {
	return $this->database->table('page')->where('id', (int) $id)->update(array(
		    'used_nette' => $nette,
		    'status' => $status,
		    'last_check' => new SqlLiteral('now()'),
	));
    }

    private function getPages($limit = 1, $offset = NULL, $status = NULL, $domain = NULL, $subdomain = 'false') {
	$pg = $this->database->table('page')->where('status', $status);
	if ($domain !== NULL) {
	    $pg->where('domain LIKE ?', "$domain");
	}
	switch ($subdomain) {
	    case NULL:
	    case 'false':
		$pg->where('subd', array('www', ''));
		break;
	    case 'true':
		break;
	    default:
		$pg->where('subd LIKE ?', "$subdomain");
		break;
	}

	return $pg->order('added ASC')->limit($limit, $offset);
    }

    private function makeLink($fromID, $toID) {
	if ($fromID == $toID) {
	    return FALSE;
	}

	try {
	    return $this->database->table('link')->insert(array(
			'from' => (int) $fromID,
			'to' => (int) $toID,
	    ));
	} catch (\PDOException $e) {
	    if ($e->getCode() == 23000) {
		return FALSE;
	    } else {
		throw $e;
	    }
	}
    }

    private function addLink($fromID, $toPage) {
	$to = $this->database->table('page')
		->where('protocol', $toPage['p'])
		->where('subd', $toPage['s'])
		->where('domain', $toPage['d'])
		->select('id')
		->fetch();

	if ($to === FALSE) {
	    $to = $this->addPage($toPage);
	}
	if ($to === FALSE) {
	    return FALSE;
	} else {
	    $toID = $to->id;
	    unset($to);
	    return $this->makeLink($fromID, $toID);
	}
    }

    /**
     * Získá url adresy z textu
     * @param string
     * @return array
     */
    private function matchPages($body) {
	preg_match_all("/<a\s[^>]*href=(\"??)(http[^\" >]*?)\\1[^>]*>.*<\/a>/siU", $body, $matchUrls);

	$urls = array();
	foreach ($matchUrls[2] as $u) {
	    $a = $this->convertUrlToArray($u);
	    if ($a) {
		$urls[] = $a;
	    }
	}
	return array_map("unserialize", array_unique(array_map("serialize", $urls)));
    }

    private function convertUrlToArray($url) {
	$parsedUrl = parse_url($url);

	if (!$parsedUrl || !isset($parsedUrl['host'])) {
	    return FALSE;
	}

	$host = explode('.', $parsedUrl['host']);

	$subdom = implode('.', array_slice($host, 0, count($host) - 2));
	$domain = implode('.', array_slice($host, count($host) - 2, 2));

	$address = ($subdom ? $subdom . '.' : "") . $domain;

	if ($parsedUrl['host'] != $address) {
	    return FALSE;
	}

	return array(
	    'p' => $parsedUrl['scheme'],
	    's' => $subdom,
	    'd' => $domain,
	    'a' => $address,
	    'u' => $parsedUrl['scheme'] . '://' . $address,
	);
    }

}
