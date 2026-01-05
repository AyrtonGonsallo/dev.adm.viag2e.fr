<?php
namespace App\Service;

use App\Entity\Invoice;
use App\Entity\DestinataireFacture ;
use Exception;
use Psr\Container\ContainerInterface;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Html2Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TotalGenerator
{
    private $path;
    private $twig;
    private $pdf_logo;

    public function __construct(ContainerInterface $container, ParameterBagInterface $params)
    {
        $this->path     = $params->get('pdf_tmp_dir');
        $this->pdf_logo = $params->get('pdf_logo_path');
        $this->twig     = $container->get('twig');
    }

    /**
     * @param array $data
     * @param array $parameters
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function generateFile(DestinataireFacture  $dest,float  $somme,array $factures, array $parameters,string $date,string $current_day)
    {
        $pdf      = new Html2Pdf('P', 'A4', 'fr');
        $fileName = "/TOTAL_{$dest->getName()}.pdf";
        try {
            $pdf->pdf->SetDisplayMode('fullpage');
            $fileName = "/TOTAL_RENTES_{$dest->getName()}.pdf";
            $pdf->writeHTML($this->twig->render('invoices/total_r.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'dest' => $dest,'somme'=>$somme,'factures'=>$factures,'periode'=>utf8_encode(strftime("%B %Y",strtotime("+1 month", time()))),'date'=>$date,'current_day'=>$current_day]));
            $pdf->output($this->path . $fileName, 'F');
            return $this->path . $fileName;
        } catch (Html2PdfException $e) {
            $pdf->clean();
            throw new Exception($e->getMessage());
        }
    }
    public function generateFile2(DestinataireFacture  $dest,float  $somme,array $factures, array $parameters,string $date,string $current_day)
    {
        $pdf2      = new Html2Pdf('P', 'A4', 'fr');
        $fileName = "/TOTAL_{$dest->getName()}.pdf";
        try {
            $pdf2->pdf->SetDisplayMode('fullpage');
            $fileName = "/TOTAL_HONORAIRES_{$dest->getName()}.pdf";
            $pdf2->writeHTML($this->twig->render('invoices/total_h.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'dest' => $dest,'somme'=>$somme,'factures'=>$factures,'date'=>$date,'periode'=>utf8_encode(strftime("%B %Y",strtotime("+1 month", time()))),'date'=>$date,'current_day'=>$current_day]));
            $pdf2->output($this->path . $fileName, 'F');
            return $this->path . $fileName;
        } catch (Html2PdfException $e) {
            $pdf2->clean();
            throw new Exception($e->getMessage());
        }
    }
	
	public function generateExcelFile2(
        array $factures
    ) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Facture');
        $sheet->setCellValue('B1', 'Nom du bien');
        $sheet->setCellValue('C1', 'Montant');
        $sheet->setCellValue('D1', 'Commentaire');
    
        $row = 2;
        foreach ($factures as $f) {
            $invoiceData = $f->getInvoice()->getData();
            $montant = number_format($invoiceData['property']['honoraryRates'], 2, ',', ' ');
            if($montant>0){
                $property = $f->getProperty();
                
                $facture = $f->getNumber();
                $nom = $property->getTitle();
        
                $commentaire = '';
                $indiceRefDate = $property->date_maj_indice_ref;
                $duhDate = $property->date_duh;
        
                if ($indiceRefDate > new \DateTime('first day of this month')) {
                    $commentaire = 'Indexation';
                } elseif ($duhDate > new \DateTime('first day of last month')) {
                    $commentaire = 'Nouveau bien';
                }
        
                $sheet->setCellValue('A' . $row, $facture);
                $sheet->setCellValue('B' . $row, $nom);
                $sheet->setCellValue('C' . $row, $montant);
                $sheet->setCellValue('D' . $row, $commentaire);
                $row++;
            }
            
        }
    
        $writer = new Xlsx($spreadsheet);
        $fileName = '/TOTAL_HONORAIRES_SAS_OG2I.xlsx';
        $writer->save($this->path . $fileName);
        return $this->path . $fileName;
    }

    public function generateExcelFile(
        array $factures
    ) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Facture');
        $sheet->setCellValue('B1', 'Nom du bien');
        $sheet->setCellValue('C1', 'Montant');
        $sheet->setCellValue('D1', 'Commentaire');
    
        $row = 2;
        foreach ($factures as $f) {
            $invoiceData = $f->getInvoice()->getData();
            $montant = number_format($invoiceData['property']['annuity'], 2, ',', ' ');
            if($montant>0){
                $property = $f->getProperty();
                
        
                $nom = $property->getTitle();
                $facture = $f->getNumber();
        
                $commentaire = '';
                $indiceRefDate = $property->date_maj_indice_ref;
                $duhDate = $property->date_duh;
        
                if ($indiceRefDate > new \DateTime('first day of this month')) {
                    $commentaire = 'Indexation';
                } elseif ($duhDate > new \DateTime('first day of last month')) {
                    $commentaire = 'Nouveau bien';
                }
                $sheet->setCellValue('A' . $row, $facture);
                $sheet->setCellValue('B' . $row, $nom);
                $sheet->setCellValue('C' . $row, $montant);
                $sheet->setCellValue('D' . $row, $commentaire);
                $row++;
            }
            
        }
    
        $writer = new Xlsx($spreadsheet);
        $fileName = '/TOTAL_RENTES_OPALE BUSINESS.xlsx';
        $writer->save($this->path . $fileName);
        return $this->path . $fileName;
    }
    
}
