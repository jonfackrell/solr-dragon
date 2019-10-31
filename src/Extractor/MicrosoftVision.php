<?php
namespace SolrDragon\Extractor;

use GoogleCloudVision\GoogleCloudVision;
use GoogleCloudVision\Request\AnnotateImageRequest;
use Omeka\Stdlib\Cli;

/**
 * Use pdftotext to extract text.
 *
 * @see https://linux.die.net/man/1/pdftotext
 */
class MicrosoftVision implements ExtractorInterface
{
    protected $cli;

    public function __construct(Cli $cli)
    {
        $this->cli = $cli;
    }

    public function isAvailable()
    {
        return true;
    }

    public function extract($filePath, array $options = [])
    {
        $pages = [];
        $dir = new \SplFileInfo(realpath($filePath));
        $iterator = new \DirectoryIterator($dir);
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        foreach ($iterator as $file) {

            if ($this->verifyFile($filePath, $file)) {
                $annotateImageRequest1 = new AnnotateImageRequest();
                $annotateImageRequest1->setImageUri("https://content.byui.edu/items/7c695bfd-084d-45f9-93c7-2f8deb6cf2ae/1/black-with-news.png?.vi=fancy");
                $annotateImageRequest1->setFeature('IMAGE_PROPERTIES');
                $annotateImageRequest1->setFeature('LABEL_DETECTION');
                $annotateImageRequest1->setFeature('TEXT_DETECTION');

                $gcvRequest = new GoogleCloudVision([$annotateImageRequest1], $settings->get('solrdragon_google_cloud_key'));
                $response = $gcvRequest->annotate();
            }

        }

        return $pages;
    }

    private function format($text)
    {
        $ob = simplexml_load_string($text);

        $array = [];
        foreach($ob->body->doc->page as $key => $page){
            $temp = [];
            //$temp['page'] = $page->attributes;
            foreach($page->word as $word){
                $temp['words'][] = [
                    'text' => (string)$word,
                    'x' => floatval((string)$word->attributes()['xMin']),
                    'y' => floatval((string)$word->attributes()['yMin']),
                    'width' => floatval((string)$word->attributes()['xMax']) - floatval((string)$word->attributes()['xMin']),
                    'height' => floatval((string)$word->attributes()['yMax']) -floatval((string)$word->attributes()['yMin'])
                ];
            }
            $array[] = $temp;
        }
        return $array;
    }

    /**
     * Verify the passed file.
     *
     * Working off the "real" base directory and "real" filepath: both must
     * exist and have sufficient permissions; the filepath must begin with the
     * base directory path to avoid problems with symlinks; the base directory
     * must be server-writable to delete the file; and the file must be a
     * readable regular file.
     *
     * @param \SplFileInfo $fileinfo
     * @return string|false The real file path or false if the file is invalid
     */
    public function verifyFile($directory, \SplFileInfo $fileinfo)
    {
        if (false === $directory) {
            return false;
        }
        $realPath = $fileinfo->getRealPath();
        if (false === $realPath) {
            return false;
        }
        if (0 !== strpos($realPath, $directory)) {
            return false;
        }
        if (!$fileinfo->isFile() || !$fileinfo->isReadable()) {
            return false;
        }
        return $realPath;
    }
}