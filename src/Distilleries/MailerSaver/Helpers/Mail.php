<?php namespace Distilleries\MailerSaver\Helpers;

use Illuminate\Config\Repository;
use Illuminate\Mail\Mailer;
use Swift_Mailer;
use Distilleries\MailerSaver\Contracts\MailModelContract;
use Wpb\String_Blade_Compiler\Factory;
use Illuminate\Events\Dispatcher;

class Mail extends Mailer {

    protected $model;
    protected $config;
    protected $override;

    public function __construct(MailModelContract $model, Repository $config, Factory $views, Swift_Mailer $swift, Dispatcher $events = null)
    {
        $this->model = $model;
        $this->config = $config;
        parent::__construct($views, $swift, $events);

    }

    /**
     * Render the given view.
     *
     * @param  string $view
     * @param  array $data
     * @return string
     */
    protected function getView($view, $data)
    {
        $body = $this->model->getTemplate($view);
        $body = (empty($body)) ? $this->views->make($view, $data)->render() : $body;

        $subject_template = $this->model->getSubject();

        if (!empty($subject_template))
        {
            $subject = view(
                array(
                    'template'   => $subject_template,
                    'cache_key'  => uniqid() . rand(),
                    'updated_at' => 0
                ),
                $data
            );

            $data['subject'] = $subject->render();
        } else
        {
            $data['subject'] = $subject_template;
        }


        $body = view(
            array(
                'template'   => $body,
                'cache_key'  => uniqid(),
                'updated_at' => 0
            ),
            $data
        );

        $data['body_mail'] = $body;
        $config = $this->config->get('mailersaver.mail');

        return $this->views->make($config['template'], $data)->render();
    }

    public function send($view, array $data = [], $callback = null)
    {


        // First we need to parse the view, which could either be a string or an array
        // containing both an HTML and plain text versions of the view which should
        // be used when sending an e-mail. We will extract both of them out here.
        list($view, $plain, $raw) = $this->parseView($view);


        $model = $this->model->initByTemplate($view);
        $template = $model->where('action',$view)->get()->last();
        $plain = (!empty($template)) ? $template->getPlain() : $plain;

        if (!empty($template))
        {
            $this->model = $template;
        }

        $data['message'] = $message = $this->createMessage();

        $this->callMessageBuilder($callback, $message);

        // Once we have retrieved the view content for the e-mail we will set the body
        // of this message using the HTML type, which will provide a simple wrapper
        // to creating view based emails that are able to receive arrays of data.
        $this->addContent($message, $view, $plain, $raw, $data);
        $this->addSubject($message);
        $this->addBcc($message);
        $this->addCc($message);
        $this->overideTo($message);

        $message = $message->getSwiftMessage();

        $this->sendSwiftMessage($message);

    }

    /**
     * @param \Illuminate\Mail\Message $message
     */
    public function addCc($message)
    {

        $cc = ($this->isOveride()) ? $this->override['cc'] : (!empty($this->model) ? $this->model->getCc() : '');

        if (!empty($cc))
        {
            $message->setCc($cc);
        }

    }

    /**
     * @param \Illuminate\Mail\Message $message
     */
    public function addBcc($message)
    {
        $bcc = ($this->isOveride()) ? $this->override['bcc'] : $this->model->getBcc();

        if (!empty($bcc))
        {
            $message->setBcc($bcc);
        }

    }

    /**
     * @param \Illuminate\Mail\Message $message
     */
    public function addSubject($message)
    {
        $subject = $this->model->getSubject();

        if (!empty($subject))
        {
            $message->setSubject($subject);
        }

    }

    public function isOveride()
    {
        $config = $this->config->get('mailersaver.mail');
        $this->override = $config['override'];

        return $this->override['enabled'];
    }

    /**
     * @param \Illuminate\Mail\Message $message
     */
    public function overideTo($message)
    {
        $to = ($this->isOveride()) ? $this->override['to'] : '';
        if (!empty($to))
        {
            $message->setTo($to);
        }
    }

} 