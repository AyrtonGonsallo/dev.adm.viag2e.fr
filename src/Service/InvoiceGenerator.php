<?php
namespace App\Service;

use App\Entity\Invoice;
use Exception;
use Psr\Container\ContainerInterface;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Html2Pdf;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class InvoiceGenerator
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
    public function generateFile(array $data, array $parameters)
    {
        $pdf      = new Html2Pdf('P', 'A4', 'fr');
        $fileName = "/invoice_{$data['number']}-file1.pdf";
        try {
            $pdf->pdf->SetDisplayMode('fullpage');
            if(empty($data['recursion']))
                $data['recursion'] = Invoice::RECURSION_MONTHLY;

            switch ($data['recursion']) {
                case Invoice::RECURSION_OTP:
					if($data['montantht']==-1){
						return -1;
					}
                        $pdf->writeHTML($this->twig->render('invoices/invoice_otp.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    

                    break;
                case Invoice::RECURSION_QUARTERLY:
                    $fileName = "/invoice_quarterly{$data['number']}-file1.pdf";
                    $pdf->writeHTML($this->twig->render('invoices/invoice_quarterly.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
					

                    break;
                case Invoice::RECURSION_MONTHLY:
                default:
                    $pdf->writeHTML($this->twig->render('invoices/invoice.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
					
                    break;
            }

            $pdf->output('/var/www/vhosts/alternativeviager.fr/dev.adm.viag2e.fr/pdf'. $fileName, 'F');
            return $this->path . $fileName;
        } catch (Html2PdfException $e) {
            $pdf->clean();
            throw new Exception($e->getMessage());
        }
    }
    public function generateFile2(array $data, array $parameters)
    {
        $pdf2      = new Html2Pdf('P', 'A4', 'fr');
        $fileName = "/invoice_{$data['number']}-file2.pdf";
        try {
            $pdf2->pdf->SetDisplayMode('fullpage');
            if(empty($data['recursion']))
                $data['recursion'] = Invoice::RECURSION_MONTHLY;

            switch ($data['recursion']) {
                case Invoice::RECURSION_OTP:
					if($data['amount']==-1){
						return -1;
					}
                        $pdf2->writeHTML($this->twig->render('invoices/invoice_otp_1.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    

                    break;
                case Invoice::RECURSION_QUARTERLY:
                    $fileName = "/invoice_quarterly{$data['number']}-file2.pdf";
                    	$pdf2->writeHTML($this->twig->render('invoices/invoice_quarterly2.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
					

                    break;
                case Invoice::RECURSION_MONTHLY:
                default:
						$pdf2->writeHTML($this->twig->render('invoices/invoice2.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
					
                    
                    break;
            }

            $pdf2->output('/var/www/vhosts/alternativeviager.fr/dev.adm.viag2e.fr/pdf'. $fileName, 'F');
            return $this->path . $fileName;
        } catch (Html2PdfException $e) {
            $pdf2->clean();
            throw new Exception($e->getMessage());
        }
    }
}
