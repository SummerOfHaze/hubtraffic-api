# HubtrafficApi

Get video information by url
```php
$api = new \HubtrafficApi\Api;
$video = $api->getVideoByUrl('http://www.pornhub.com/view_video.php?viewkey=ph57c67facc4ab2'); 
```

Or by source and id
```php
$api = new \HubtrafficApi\Api;
$details = $api->parseSourceAndId('http://www.pornhub.com/view_video.php?viewkey=ph57c67facc4ab2');
$video = $this->getVideo($details['source'], $details['id']);
```

Check if video is active
```php
$video; // some video
$api = new \HubtrafficApi\Api;
if (!$this->isVideoActive($video->source, $video->sourceId)) {
	// delete
}
```

Supported servers: redtube.com, pornhub.com, youporn.com, tube8.com, spankwire.com
