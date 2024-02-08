<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\BankExport;
use App\Entity\File;
use App\Entity\Warrant;
use App\Entity\Property;
use DateTime;
use App\Form\FileFormType;
use App\Service\DriveManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FilesListController extends AbstractController
{

   

/**
     * @Route("/fileslist/data", name="fileslist_data")
     *
     * @param Request $request
     * @return Response
     */
    public function data(Request $request)
    {
        $pagination = $request->get('pagination');
        $query = $request->get('query');

        if(empty($query)) {
            $query = [];
        }

        $page = !empty($pagination['page']) ? $pagination['page'] : 1;
        $perpage = !empty($pagination['perpage']) ? $pagination['perpage'] : self::RES_PER_PAGE;

        $start = new DateTime('-2 months');
        $end = new DateTime();

        if(!empty($query['range'])) {
            $dates = explode(' - ', $query['range']);
            if (count($dates) == 2) {
                $start = DateTime::createFromFormat('d/m/Y', $dates[0]);
                $end   = DateTime::createFromFormat('d/m/Y', $dates[1]);
            }
        }

        if ($start === false || $end === false) {
            $start = new DateTime('first day of 2 months ago');
            $end = new DateTime();
        }

        $query['start'] = $start;
        $query['end'] = $end;

        $rep = $this->getDoctrine()->getManager()->getRepository(File::class);

        $files = $rep->findAllOrdered($page, $perpage, $query);
        function get_type_label($i){
            if($i==1){
                return "Document";
            }else if ($i==2){
                return "Facture";
            }else{
                return "Décompte annuel";
            }
        }

        $data = [];

        /** @var Invoice $invoice */
        foreach ($files['data'] as $file) {
            
			
            $data[] = [
                'Selected' =>"<input type='checkbox' name='file_".$file->getId()."' value='invoice_".$file->getId()."'>",
                'Name' => $file->getName(),
                'Date' => utf8_encode(strftime("%A %d %B %Y",strtotime($file->getDate()->format('Y-m-d H:i:s')))),
                'Number' => ($file->getInvoice())?$file->getInvoice()->getNumber():"",
                'Type' => get_type_label($file->getType()),
                'Download' => '<a href="'.$this->generateUrl('file_download', ['fileId' => $file->getDriveId()]).'" target="_blank"><i class="la la-cloud-download" title="Télécharger"></i> Télécharger</a>',
            ];
        }

        return JsonResponse::fromJsonString(json_encode([
            'post' => json_encode($_POST),
            'meta' => [
                'page'    => $page,
                'pages'   => $files['pagesCount'],
                'perpage' => $perpage,
                'total'   => $files['total'],
                'sort'    => 'asc',
                'field'   => 'Date'
            ], 
            'data' => $data
        ]));
    }


    
	/**
     * @Route("/file/list", name="files_list")
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function view(Request $request){

        $range = $request->get('range');

        $start = new DateTime('-2 months');
        $end = new DateTime();

        $dates = explode(' - ', $range);
        if (count($dates) == 2) {
            $start = DateTime::createFromFormat('d/m/Y', $dates[0]);
            $end = DateTime::createFromFormat('d/m/Y', $dates[1]);
        }

        if ($start === false || $end === false) {
            $start = new DateTime('first day of 2 months ago');
            $end = new DateTime();
        }

        return $this->render('file/list-files.html.twig', [
            'range_start' => $start->format('d/m/Y'),
            'range_end' => $end->format('d/m/Y'),
        ]);


        
    }


    
/**
     *@Route(name="get_selected_files",path="/get_selected_files/{type}")
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function get_selected_files(Request $request)
    {
        $type=$request->get('type');
        $ids=$request->get('ids');
        $results=array();
        $manager = $this->getDoctrine()->getManager();

        if($type=="télécharger-tous"){
            foreach ($ids as $id) {
                if($id["id"]>0){
                    $file = $this->getDoctrine()
                    ->getRepository(File::class)
                    ->find($id["id"]);
                    if($file){
                        
                        array_push($results,array('/file/download/'.$file->getDriveId(),$file));

                    }
                    
                }
                
            }
            $rep="télécharger";

        }
        $manager->flush();
        return new JsonResponse(['selected_data' => $ids,"results"=> $results,"type"=> $type],);
    }


}
