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
class GoogleVision implements ExtractorInterface
{
    protected $settings;
    protected $cli;

    public function __construct($settings, Cli $cli)
    {
        $this->settings = $settings;
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

        foreach ($iterator as $file) {

            if ($this->verifyFile($filePath, $file)) {
                $annotateImageRequest1 = new AnnotateImageRequest();
                // $annotateImageRequest1->setImage($filePath.DIRECTORY_SEPARATOR.$file);
                $annotateImageRequest1->setImage( base64_encode( file_get_contents($filePath . DIRECTORY_SEPARATOR . $file ) ) );
                $annotateImageRequest1->setFeature('IMAGE_PROPERTIES');
                $annotateImageRequest1->setFeature('LABEL_DETECTION');
                $annotateImageRequest1->setFeature('DOCUMENT_TEXT_DETECTION');

                $gcvRequest = new GoogleCloudVision([$annotateImageRequest1], $this->settings->get('solrdragon_google_cloud_key'));
                $response = $gcvRequest->annotate();
                $pages[] = $this->format($response->responses[0]->textAnnotations);
            }

        }

        return $pages;
    }

    private function format($json)
    {

        $array = [];
        foreach($json as $key => $word){
            if(!property_exists($word, 'locale')){
                if( property_exists($word->boundingPoly->vertices[0], 'x')
                    && property_exists($word->boundingPoly->vertices[0], 'y')
                    && property_exists($word->boundingPoly->vertices[1], 'x')
                    && property_exists($word->boundingPoly->vertices[2], 'y')
                ){
                    try{
                        $array['words'][] = [
                            'text' => $word->description,
                            'x' => floatval($word->boundingPoly->vertices[0]->x),
                            'y' => floatval($word->boundingPoly->vertices[0]->y),
                            'width' => floatval($word->boundingPoly->vertices[1]->x) - floatval($word->boundingPoly->vertices[0]->x),
                            'height' => floatval($word->boundingPoly->vertices[2]->y) - floatval($word->boundingPoly->vertices[0]->y)
                        ];
                    }catch(\Exception $error){

                    }
                }
            }
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