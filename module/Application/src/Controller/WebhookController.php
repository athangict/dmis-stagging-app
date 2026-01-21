<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\Http\Request;
use Laminas\Log\Logger;
use Laminas\Log\Writer\Stream;

class WebhookController extends AbstractActionController
{
    public function receiveAction()
    {
        $request = $this->getRequest();
        //
        if (!$request->isPost()) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'Invalid request method',
            ]);
        }

        // Get the raw POST data
        $content = $request->getContent();
        $data = json_decode($content, true);
		echo '<pre>';print_r($data);exit;
        // Log the webhook data for debugging purposes
        $logger = new Logger();
        $writer = new Stream('path/to/your/logfile.log'); // Update the path as needed
        $logger->addWriter($writer);
        $logger->info('Webhook received: ' . print_r($data, true));

        // Process the data as needed
        // You can add your logic here to handle different types of webhook events

        return new JsonModel([
            'status' => 'success',
            'message' => 'Webhook received successfully',
        ]);
    }
}
