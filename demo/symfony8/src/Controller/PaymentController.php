<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\Redsys\Currency;
use Nowo\Redsys\Environment;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Notification;
use Nowo\Redsys\RedirectForm;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\TransactionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentController extends AbstractController
{
    private function merchant(): Merchant
    {
        $env = ($_ENV['REDSYS_ENV'] ?? 'test') === 'live' ? Environment::Live : Environment::Test;

        return new Merchant(
            (string) ($_ENV['REDSYS_MERCHANT_CODE'] ?? ''),
            (string) ($_ENV['REDSYS_TERMINAL'] ?? '1'),
            (string) ($_ENV['REDSYS_SECRET_KEY'] ?? ''),
            $env,
            SignatureVersion::HmacSha512V2,
        );
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('payment/home.html.twig', [
            'defaultOrder' => substr((string) time(), -10),
        ]);
    }

    #[Route('/pay', name: 'pay', methods: ['POST'])]
    public function pay(Request $request): Response
    {
        $amount = (string) $request->request->get('amount', '145');
        $order = (string) $request->request->get('order', substr((string) time(), -10));
        $base = rtrim((string) ($_ENV['DEFAULT_URI'] ?? $request->getSchemeAndHttpHost()), '/');

        $merchant = $this->merchant();
        $params = MerchantParameters::create()
            ->forMerchant($merchant)
            ->amount($amount)
            ->order($order)
            ->currency(Currency::Eur)
            ->transactionType(TransactionType::Authorization)
            ->merchantUrl($base.'/notify')
            ->urlOk($base.'/ok')
            ->urlKo($base.'/ko')
            ->productDescription('RedsysPhp demo payment');

        $html = RedirectForm::forMerchant($merchant, $params);

        return new Response($html);
    }

    #[Route('/notify', name: 'notify', methods: ['POST'])]
    public function notify(Request $request): Response
    {
        try {
            $notification = Notification::fromRequest($request->request->all(), $this->merchant());
            $authorized = $notification->isAuthorized();
            $code = $notification->responseCode() ?? 'n/a';

            return new Response(
                $authorized ? 'OK '.$code : 'KO '.$code,
                Response::HTTP_OK,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        } catch (\Throwable $e) {
            return new Response('ERROR '.$e->getMessage(), Response::HTTP_BAD_REQUEST, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }

    #[Route('/ok', name: 'ok', methods: ['GET', 'POST'])]
    public function ok(): Response
    {
        return $this->render('payment/result.html.twig', [
            'title' => 'Payment return OK',
            'tone' => 'ok',
        ]);
    }

    #[Route('/ko', name: 'ko', methods: ['GET', 'POST'])]
    public function ko(): Response
    {
        return $this->render('payment/result.html.twig', [
            'title' => 'Payment return KO',
            'tone' => 'ko',
        ]);
    }
}
