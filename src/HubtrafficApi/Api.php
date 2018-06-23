<?php

namespace HubtrafficApi;

/**
 * Hubtraffic api wrapper
 * @author Pavel Plzák <pavelplzak@protonmail.com>
 * @license MIT
 * @version 1.1.1
 * @package HubtrafficApi
 */
class Api {

	const SOURCE_REDTUBE = 'redtube';

	const SOURCE_PORNHUB = 'pornhub';

	const SOURCE_YOUPORN = 'youporn';

	const SOURCE_TUBE8 = 'tube8';

	const SOURCE_SPANKWIRE = 'spankwire';


	/** @var array */
	private $config = [
		self::SOURCE_REDTUBE => [
			'serverName' => 'RedTube.com',
			'pattern' => '/([0-9]+)',
			'url' => [
				'video' => 'http://api.redtube.com/?data=redtube.Videos.getVideoById&output=json&thumbsize=big&video_id=',
				'embed' => 'http://api.redtube.com/?data=redtube.Videos.getVideoEmbedCode&output=json&video_id=',
				'active' => 'http://api.redtube.com/?data=redtube.Videos.isVideoActive&output=json&video_id=',
			]
		],
		self::SOURCE_PORNHUB => [
			'serverName' => 'PornHub.com',
			'pattern' => '/view_video.php\?viewkey=([a-z0-9]+)',
			'url' => [
				'video' => 'http://www.pornhub.com/webmasters/video_by_id?thumbsize=large_hd&id=',
				'embed' => 'http://www.pornhub.com/webmasters/video_embed_code?id=',
				'active' => 'http://www.pornhub.com/webmasters/is_video_active?id=',
			]
		],
		self::SOURCE_YOUPORN => [
			'serverName' => 'YouPorn.com',
			'pattern' => '/watch/([0-9]+)/.*',
			'url' => [
				'video' => 'http://www.youporn.com/api/webmasters/video_by_id/?output=json&thumbsize=big&video_id=',
				'embed' => 'http://www.youporn.com/api/webmasters/video_embed_code/?video_id=',
				'active' => 'http://www.youporn.com/api/webmasters/is_video_active/?video_id=',
			]
		],
		self::SOURCE_TUBE8 => [
			'serverName' => 'Tube8.com',
			'pattern' => '/.*/.*/([0-9]+)',
			'url' => [
				'video' => 'http://api.tube8.com/api.php?action=getvideobyid&output=json&thumbsize=big&video_id=',
				'embed' => 'http://api.tube8.com/api.php?action=getvideoembedcode&output=json&video_id=',
				'active' => 'http://api.tube8.com/api.php?action=isvideoactive&output=json&video_id=',
			]
		],
		self::SOURCE_SPANKWIRE => [
			'serverName' => 'SpankWire.com',
			'pattern' => '/.*/video([0-9]+)',
			'url' => [
				'video' => 'http://www.spankwire.com/api/HubTrafficApiCall?data=getVideoById&thumbsize=large&output=json&video_id=',
				'embed' => 'http://www.spankwire.com/api/HubTrafficApiCall?data=getVideoEmbedCode&output=json&video_id=',
				'active' => 'http://www.spankwire.com/api/HubTrafficApiCall?data=isVideoActive&output=json&video_id=',
			]
		],
	];

	/** @var array */
	private $proxies = [];


	/**
	 * Returns config for concrete source
	 * @param string $source
	 * @return array
	 * @throws UnsupportedSourceException
	 */
	public function getConfig($source) {
		if (!array_key_exists($source, $this->config)) {
			throw new UnsupportedSourceException('Source not found');
		}

		return $this->config[$source];
	}


	/**
	 * Returns all supported sources
	 * @return array
	 */
	public function getSupportedSources() {
		return array_keys($this->config);
	}


	/**
	 * Array of proxy ips to use
	 * @param array $proxies
	 */
	public function setProxies(array $proxies) {
		$this->proxies = $proxies;
	}




	/**
	 * Returns video object
	 * @param string $url
	 * @return \HubtrafficApi\Video
	 */
	public function getVideoByUrl($url) {
		$details = $this->parseSourceAndId($url);
		return $this->getVideo($details['source'], $details['id']);
	}


	/**
	 * Returns video object by source and video id
	 * @param string $source
	 * @param string $id
	 * @return \HubtrafficApi\Video|false
	 */
	public function getVideo($source, $id) {
		$config = $this->getConfig($source);

		$videoData = $this->getApiData($config['url']['video'] . $id);
		if (empty($videoData->video)) {
			return false;
		}

		$video = $this->getDataParser($source)->parseVideoData($source, $id, $videoData);

		$embedData = $this->getApiData($config['url']['embed'] . $id);
		if ($embedData) {
			$embed = $this->getDataParser($source)->parseEmbedData($embedData);
			if ($embed) {
				$parts = explode('</iframe>', $embed);
				$video->setEmbed($parts[0] . '</iframe>');
			}
		}

		return $video;
	}


	/**
	 * Checks if video is active
	 * @param $source
	 * @param $id
	 * @return bool
	 */
	public function isVideoActive($source, $id) {
		$config = $this->getConfig($source);
		$data = $this->getApiData($config['url']['active'] . $id);

		return $this->getDataParser($source)->parseIsActive($data);
	}


	/**
	 * Parses video url and returns source and video id
	 * @param string $videoUrl
	 * @throws UnsupportedSourceException
	 * @return array|false
	 */
	public function parseSourceAndId($videoUrl) {
		preg_match('~(?:www\.)?([a-z0-9]+)\.[a-z]+~', parse_url($videoUrl, PHP_URL_HOST), $sourceMatches);
		$source = $sourceMatches[1];

		if (!array_key_exists($source, $this->config)) {
			throw new UnsupportedSourceException('Unsupported source.');
		}

		preg_match('~'.$this->config[$source]['pattern'].'~', $videoUrl, $videoIdMatches);
		$videoId = $videoIdMatches[1];

		if (!empty($videoId)) {
			return [ 'source' => $source, 'id' => $videoId ];
		}

		return false;
	}


	/**
	 * Sends request to api url and parse json result
	 * @param string $url
	 * @return \stdClass|null
	 */
	private function getApiData($url) {
		$videoData = null;
		$c = curl_init($url);
		curl_setopt($c, CURLOPT_HEADER, FALSE);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($c, CURLOPT_TIMEOUT, 5);

		if ($this->proxies) {
			$triedProxies = [];
			while ($remainingProxies = array_diff($this->proxies, $triedProxies)) {
				$triedProxies[] = $proxy = $remainingProxies[array_rand($remainingProxies)];
				curl_setopt($c, CURLOPT_PROXY, $proxy);

				$result = curl_exec($c);

				if ($result) {
					if ($decodedResult = json_decode($result)) {
						$videoData = $decodedResult;
						break;
					}
				}
			}
		} else {
			$result = curl_exec($c);
			if ($result) {
				if ($decodedResult = json_decode($result)) {
					$videoData = $decodedResult;
				}
			}
		}

		return $videoData;
	}


	/**
	 * Returns instance of data parser
	 * @param string $source
	 * @throws \Exception
	 * @return IDataParser
	 */
	private function getDataParser($source) {
		$parserClassName = '\\' . __NAMESPACE__ . '\\' . ucfirst($source) . 'DataParser';
		if (class_exists($parserClassName)) {
			return new $parserClassName;
		} else {
			throw new \Exception('Data parser class not found');
		}
	}

}


class UnsupportedSourceException extends \Exception {};