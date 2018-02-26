<?php

namespace BddBundle\Controller;

use BddBundle\Entity\LikeReport;
use BddBundle\Entity\Report;
use BddBundle\Form\Type\ReportCreateType;
use BddBundle\Form\Type\ReportWriteType;
use BddBundle\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
     * @param FileUploader $fileUploader
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $report = new Report();
        $report->setUser($this->getUser());

        $form = $this->createForm(
            ReportCreateType::class,
            $report,
            [
                'languages' => $this->getParameter('app.locales.array'),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $cover */
            $cover = $report->getCover();
            $fileName = $fileUploader->upload($cover);
            $report->setCover($fileName);

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
     * @Route("/{id}/write", requirements={"id" = "\d+"}, name="reports_write")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     * @param Request $request
     * @param Report $report
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function writeAction(Request $request, Report $report)
    {
        $this->denyAccessUnlessGranted('edit', $report);

        $form = $this->createForm(ReportWriteType::class, $report);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($report);
            $em->flush();

            return $this->redirectToRoute('reports_write', ['slug' => $report->getId()]);
        }

        return $this->render(
            'BddBundle:Report:write.html.twig',
            [
                'form' => $form->createView(),
                'report' => $report,
            ]
        );
    }

    /**
     * @Route("/{id}/content", requirements={"id" = "\d+"}, name="reports_update_content", options = {"expose" = true})
     * @Method({"POST"})
     * @Security("is_granted('ROLE_USER')")
     * @param Request $request
     * @param Report $report
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function updateContentAction(Request $request, Report $report)
    {
        $this->denyAccessUnlessGranted('edit', $report);

        $content = $request->request->get('content');
        $report->setContent($content);

        $em = $this->getDoctrine()->getManager();
        $em->persist($report);
        $em->flush();

        return new JsonResponse(['state' => 'ok']);
    }

    /**
     * @Route("/{id}", requirements={"id" = "\d+"}, name="reports_show")
     * @Method({"GET"})
     * @param Report $report
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function showAction(Report $report, EntityManagerInterface $em)
    {
        $em->getRepository('BddBundle:Report')->addView($report);

        return $this->render(
            'BddBundle:Report:show.html.twig',
            [
                'report' => $report,
            ]
        );
    }

    /**
     * @Route("/{id}/like", name="reports_toogle_like")
     * @Method({"GET"})
     * @Security("is_granted('ROLE_USER')")
     * @param Report $report
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    public function toggleLikeAction(Report $report, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('like', $report);

        $user = $this->getUser();
        $like = $em->getRepository('BddBundle:LikeReport')->findOneBy(['user' => $user, 'report' => $report]);

        if ($like instanceof LikeReport) {
            $em->remove($like);
            $em->flush();

            return new JsonResponse(['like' => false]);
        }

        $like = new LikeReport();
        $like->setUser($user)->setReport($report);
        $em->persist($like);
        $em->flush();

        return new JsonResponse(['like' => true]);
    }

    /**
     * @Route("/upload", name="reports_upload")
     * @param Request $request
     * @param FileUploader $fileUploader
     * @return JsonResponse
     * @throws \Exception
     */
    public function uploadImageAction(Request $request, FileUploader $fileUploader)
    {
        $image = $request->files->get('image');

        if (!$image instanceof UploadedFile) {
            throw new \Exception('error');
        }

        $fileName = $fileUploader->upload($image);

        $url = 'http://localhost:8000/uploads/reports/images/'.$fileName;

        return new JsonResponse(['url' => $url]);
    }

    /**
     * @Route("/", name="reports_list")
     * @Method({"GET"})
     * @return Response
     */
    public function listAction()
    {
        $em = $this->getDoctrine()->getManager();
        $reports = $em->getRepository('BddBundle:Report')->findBy([], ['updateDate' => 'desc'], 10);

        return $this->render(
            'BddBundle:Report:list.html.twig',
            [
                'reports' => $reports,
            ]
        );
    }
}
