<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Report;
use BddBundle\Form\Type\ReportCreateType;
use BddBundle\Form\Type\ReportWriteType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ReportController
 * @package BddBundle\Controller
 * @Route("/reports")
 */
class ReportController extends Controller
{
    /**
     * @Route("/new", name="reports_new")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request)
    {
        $report = new Report();
        $report->setUser($this->getUser());

        /** @var Form $form */
        $form = $this->createForm(ReportCreateType::class, $report, [
            'languages' => $this->getParameter('app.locales.array')
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($report);
            $em->flush();

            return $this->redirectToRoute('reports_write', ['id' => $report->getId()]);
        }

        return $this->render(
            'BddBundle:Report:new.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @Route("/{id}/write", name="reports_write")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     * @param Request $request
     * @param Report $report
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, Report $report)
    {
//        $this->denyAccessUnlessGranted('edit', $report);

        /** @var Form $form */
        $form = $this->createForm(ReportWriteType::class, $report);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($report);
            $em->flush();

            return $this->redirectToRoute('reports_write', ['slug' => $report->getId()]);
        }

        return $this->render(
            'BddBundle:Report:edit.html.twig',
            [
                'form' => $form->createView(),
                'report' => $report
            ]
        );
    }

    /**
     * @Route("/{id}/content", name="reports_content")
     * @Method({"POST"})
     * @Security("is_granted('ROLE_USER')")
     * @param Request $request
     * @param Report $report
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function contentAjaxAction(Request $request, Report $report)
    {
        $content = $request->request->get('content');
        $report->setContent($content);

        $em = $this->getDoctrine()->getManager();
        $em->persist($report);
        $em->flush();

        return new JsonResponse(['state' => 'ok']);
    }
}
