<?php

namespace App\Controller;

use App\Entity\BankExport;
use App\Entity\File;
use App\Entity\Warrant;
use App\Entity\Property;
use App\Form\FileFormType;
use App\Service\DriveManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileController extends AbstractController
{
    /**
     * @Route("/warrant/file/add/{warrantId}", name="file_add", requirements={"warrantId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function add(Request $request, DriveManager $driveManager)
    {
        $warrant = $this->getDoctrine()
            ->getRepository(Warrant::class)
            ->find($request->get('warrantId'));

        if (empty($warrant)) {
            $this->addFlash('danger', 'Mandat introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $file = new File();
        $form = $this->createForm(FileFormType::class, $file, ['action' => $this->generateUrl('file_add', ['warrantId' => $warrant->getId()])]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $f */
            $f = $file->getDriveId();
            $file->setTmpName(md5(uniqid()).'.'.$f->guessExtension());
            $file->setMime($f->getMimeType());
            $f->move($this->getParameter('tmp_files_dir'), $file->getTmpName());

            $filePath = $this->getParameter('tmp_files_dir').'/'.$file->getTmpName();
            $file->setDriveId($driveManager->addFile($file->getName(), $filePath, File::TYPE_DOCUMENT, $warrant->getId()));
            $file->setType(File::TYPE_DOCUMENT);
            $file->setWarrant($warrant);

            $manager = $this->getDoctrine()->getManager();
            $manager->persist($file);
            $manager->flush();

            unlink($filePath);
        }

        return $this->redirectToRoute('warrant_view', ['type' => Warrant::getTypeName($warrant->getType()), 'warrantId' => $warrant->getId()]);
    }

    /**
     * @Route("/file/delete/{fileId}", name="file_delete")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function delete(Request $request, DriveManager $driveManager)
    {
        $file = $this->getDoctrine()
            ->getRepository(File::class)
            ->findOneBy(['drive_id' => $request->get('fileId')]);

        $route = $this->generateUrl('warrant_view', ['type' => $file->getWarrant()->getTypeString(), 'warrantId' => $file->getWarrant()->getId()]);

        if (empty($file) || $file->getType() != File::TYPE_DOCUMENT) {
            $this->addFlash('danger', 'Fichier introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        if ($driveManager->trashFile($file) === true) {
            $manager = $this->getDoctrine()->getManager();
            $manager->remove($file);
            $manager->flush();

            $this->addFlash('success', 'Fichier supprimé');
            return $this->redirect($route);
        }

        $this->addFlash('danger', 'Une erreur a eu lieu pendant la suppression');
        return $this->redirect($route);
    }



    /**
     * @Route("/property/file/add/{propertyId}", name="property_file_add", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function add_property_file(Request $request, DriveManager $driveManager)
    {
        $property = $this->getDoctrine()
        ->getRepository(Property::class)
        ->find($request->get('propertyId'));

        $warrant = $property->getWarrant();

        if (empty($warrant)) {
            $this->addFlash('danger', 'Mandat introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $file = new File();
        $form = $this->createForm(FileFormType::class, $file, ['action' => $this->generateUrl('file_add', ['warrantId' => $warrant->getId()])]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $f */
            $f = $file->getDriveId();
            $file->setTmpName(md5(uniqid()).'.'.$f->guessExtension());
            $file->setMime($f->getMimeType());
            $file->setDate(new DateTime());
            $f->move($this->getParameter('tmp_files_dir'), $file->getTmpName());

            $filePath = $this->getParameter('tmp_files_dir').'/'.$file->getTmpName();
            $file->setDriveId($driveManager->addFile($file->getName(), $filePath, File::TYPE_DOCUMENT, $warrant->getId()));
            $file->setType(File::TYPE_DOCUMENT);
            $file->setWarrant($warrant);

            $manager = $this->getDoctrine()->getManager();
            $manager->persist($file);
            $manager->flush();

            unlink($filePath);
        }

        return $this->redirectToRoute('property_view', [ 'propertyId' => $property->getId()]);
    }

    /**
     * @Route("/file/delete/{fileId}", name="file_delete")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function delete_property_file(Request $request, DriveManager $driveManager)
    {
        $file = $this->getDoctrine()
            ->getRepository(File::class)
            ->findOneBy(['drive_id' => $request->get('fileId')]);

        $route = $this->generateUrl('property_view', ['type' => $file->getProperty()->getTypeString(), 'propertyId' => $file->getProperty()->getId()]);

        if (empty($file) || $file->getType() != File::TYPE_DOCUMENT) {
            $this->addFlash('danger', 'Fichier introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        if ($driveManager->trashFile($file) === true) {
            $manager = $this->getDoctrine()->getManager();
            $manager->remove($file);
            $manager->flush();

            $this->addFlash('success', 'Fichier supprimé');
            return $this->redirect($route);
        }

        $this->addFlash('danger', 'Une erreur a eu lieu pendant la suppression');
        return $this->redirect($route);
    }
    /**
     * @Route("/file/download/{fileId}", name="file_download")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function download(Request $request, DriveManager $driveManager)
    {
        /** @var File $file */
        $file = $this->getDoctrine()
            ->getRepository(File::class)
            ->findOneBy(['drive_id' => $request->get('fileId')]);

        if (empty($file)) {
            $this->addFlash('danger', 'Fichier introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $path = $driveManager->getFile($file);
        if (!empty($path)) {
            $response = new BinaryFileResponse($path);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getName().$file->getExtension());
            //$response->headers->set('Content-Type', $file->getMime());
            $response->deleteFileAfterSend(true);
            return $response;
        }

        $this->addFlash('danger', 'Fichier introuvable');
        return $this->redirectToRoute('dashboard', [], 302);
    }

    /**
     * @Route("/file/download2/{fileId}", name="file_download2")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function download2(Request $request, DriveManager $driveManager)
    {
        /** @var File $file */
        $file = $this->getDoctrine()
            ->getRepository(File::class)
            ->findOneBy(['drive_id' => $request->get('fileId')]);

        if (empty($file)) {
            $this->addFlash('danger', 'Fichier introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }
        if($file->getType()==File::TYPE_DOCUMENT){
            $path = '/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $file->getName();
            if (!empty($path)) {
                return  $this->file( $path);
            }
        }else{
            $path = $driveManager->getFile($file);
            if (!empty($path)) {
                $response = new BinaryFileResponse($path);
                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getName().$file->getExtension());
                //$response->headers->set('Content-Type', $file->getMime());
                $response->deleteFileAfterSend(true);
                return $response;
            }
        }
        

        $this->addFlash('danger', 'Fichier introuvable');
        return $this->redirectToRoute('dashboard', [], 302);
    }

    /**
     * @Route("/file/download/export/{fileId}", name="file_export_download")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function downloadExport(Request $request, DriveManager $driveManager)
    {
        /** @var BankExport $export */
        $export = $this->getDoctrine()
            ->getRepository(BankExport::class)
            ->findOneBy(['drive_id' => $request->get('fileId')]);

        if (empty($export)) {
            $this->addFlash('danger', 'Fichier introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $path = $driveManager->getExport($export);
        if (!empty($path)) {
            $response = new BinaryFileResponse($path);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $export->getName());
            //$response->headers->set('Content-Type', $file->getMime());
            $response->deleteFileAfterSend(true);
            return $response;
        }

        $this->addFlash('danger', 'Fichier introuvable');
        return $this->redirectToRoute('dashboard', [], 302);
    }
	/**
     * @Route("/file/list", name="files_list")
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function view(Request $request){
        $fichiers = $this->getDoctrine()
        ->getRepository(File::class)
        ->findBy(array(), ['id' => 'DESC'], 100, 0);

        if (empty($fichiers)) {
            $this->addFlash('danger', 'Aucun honoraire n\'est enregistré');
        }
        return $this->render('file/list-files.html.twig', [
            
            'fichiers'  => $fichiers,
            
            
        ]);
    }
}
