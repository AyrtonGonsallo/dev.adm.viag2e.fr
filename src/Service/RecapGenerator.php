<?php
namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Recap;
use App\Entity\Warrant;
use Exception;
use Psr\Container\ContainerInterface;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Html2Pdf;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class RecapGenerator
{
    private $path;
    private $twig;
    private $pdf_logo;

    public function __construct(ContainerInterface $container, ParameterBagInterface $params)
    {
        $this->path     = $params->get('pdf_tmp_dir').'/recap';
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
        $fileName = ($data['type'] === Recap::TYPE_FULL) ? "/recap_{$data['year']}_{$data['property']['id']}.pdf" : "/recap_{$data['year']}_{$data['property']['id']}_{$data['type']}.pdf";
        try {
            $pdf->pdf->SetDisplayMode('fullpage');
            if(empty($data['recursion']))
                $data['recursion'] = Invoice::RECURSION_MONTHLY;

            if ($data['type'] === Recap::TYPE_FULL) {
                $pdf->writeHTML($this->twig->render('recap/full_recap.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
            }
            else {
                if ($data['warrant']['type'] === Warrant::TYPE_SELLERS) {
                    switch ($data['type']) {
                        case Recap::TYPE_BUYER:
                            $pdf->writeHTML($this->twig->render('recap/seller_buyer.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                            break;
                        case Recap::TYPE_SELLER:
                            $pdf->writeHTML($this->twig->render('recap/seller_seller.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                            break;
                        case Recap::TYPE_HONORARIES:
                        default:
                            $pdf->writeHTML($this->twig->render('recap/honoraries_seller.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                            break;
                    }
                } else {
                    switch ($data['type']) {
                        case Recap::TYPE_BUYER:
                            $pdf->writeHTML($this->twig->render('recap/buyer_buyer.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                            break;
                        case Recap::TYPE_SELLER:
                            $pdf->writeHTML($this->twig->render('recap/buyer_seller.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                            break;
                        case Recap::TYPE_HONORARIES:
                        default:
                            $pdf->writeHTML($this->twig->render('recap/honoraries_buyer.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                            break;
                    }
                }
            }

            $pdf->output($this->path . $fileName, 'F');
            return $this->path . $fileName;
            //return $pdf->output('','S');
        } catch (Html2PdfException $e) {
            $pdf->clean();
            throw new Exception($e->getMessage());
        }
    }
}
