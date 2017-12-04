<?php

namespace TrackBundle\Controller;

use TrackBundle\Entity\Product;
use TrackBundle\Entity\ProductAttribute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;


/**
 * Product controller.
 *
 * @Route("track")
 */
class ProductController extends Controller
{
    /**
     * Lists all product entities.
     *
     * @Route("/index/{page}/{sortBy}", name="track_index", defaults={"page" = 1, "sortBy" = "updatedAt=DESC"})
     * @Method("GET")
     */
    public function indexAction($page, $sortBy)
    {
        $em = $this->getDoctrine()->getManager();
        
        $order = $em->getRepository('TrackBundle:Product')->serializeSort($sortBy);
        $products = $em->getRepository('TrackBundle:Product')->findSpecific($order);

        return $this->render('product/index.html.twig', array(
            'products' => $products,
        ));
    }

    /**
     * Creates a new product entity.
     *
     * @Route("/new", name="track_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $product = new Product();
        $form = $this->createForm('TrackBundle\Form\ProductType', $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            
            // check for sku
            $skuquery = $em->createQuery(
                    'SELECT p.sku'
                    . ' FROM TrackBundle:Product p'
                    . ' WHERE p.sku = :givensku')
                    ->setParameter('givensku', $product->getSku());
            $result = $skuquery->getResult();
            
            if(count($result)==0)
            {
                $em->persist($product);
                $em->flush($product);
            
                // fill in product id if sku is left blank
            
                //

                return $this->redirectToRoute('track_show', array('id' => $product->getId()));
            } 
             else 
            {
                return $this->render('product/new.html.twig', array(
                    'product' => $product,
                    'form' => $form->createView(),
                    'error_msg' => 'DuplicateSku',
                ));
            }
            
            
        }

        return $this->render('product/new.html.twig', array(
            'product' => $product,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a product entity.
     *
     * @Route("/{id}/show", name="track_show")
     * @Method("GET")
     */
    public function showAction(Product $product)
    {
        $deleteForm = $this->createDeleteForm($product);
        
        // get attributes
        $em = $this->getDoctrine()->getManager();
        
        $query = $em->createQuery(
                'SELECT a'
                . ' FROM TrackBundle:ProductAttribute a'
                . ' WHERE a.productid = :productid'
                )->setParameter('productid', $product->getId());
        
        $attributes = $query->getResult();

        return $this->render('product/show.html.twig', array(
            'product' => $product,
            'attributes' => $attributes,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing product entity.
     *
     * @Route("/{id}/edit", name="track_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Product $product)
    {
        $em = $this->getDoctrine()->getManager();

        // create form for deleting
        $deleteForm = $this->createDeleteForm($product);
        
        // create form for editing
        $editForm = $this->createForm('TrackBundle\Form\ProductType', $product);
        $editForm->handleRequest($request);
        
        if($product->getType() != null) {
            // check for attributes
            $query = $em->createQuery('SELECT '
                    . '     pa.*'
                    . 'FROM'
                    . '     TrackBundle:ProductAttribute pa '
                    . 'WHERE'
                    . '     pa.productid = :id')
                    ->setParameter('id', $product->getId());

            // no attributes, apply attributes from product type
            $attr_c = $query->getResult();

            if(count($attr_c)==0) {
                $this->applyAttributeTemplate($product);
            }
        }
        
        
        if ($editForm->isSubmitted() && $editForm->isValid()) {
            
            // check for sku
            $skuquery = $em->createQuery(
                    'SELECT p.sku'
                    . ' FROM TrackBundle:Product p'
                    . ' WHERE p.sku = :givensku'
                    . ' AND p.id <> :id')
                    ->setParameter('givensku', $product->getSku())
                    ->setParameter('id', $product->getId());
            $result = $skuquery->getResult();
            
            if(count($result)==0)
            {
                $em->persist($product);
                $em->flush($product);
            
                // fill in product id if sku is left blank
            
                //

                return $this->redirectToRoute('track_show', array('id' => $product->getId()));
            } 
            else 
            {
                return $this->redirectToRoute('track_edit', array('id' => $product->getId()));
            }
        }
        
        // get attributes (previously checked or added)
        $query = $em->createQuery('SELECT'
                . '     pa.id'
                . '     a.name'
                . '     pa.value'
                . 'FROM'
                . '     TrackBundle:ProductAttribute pa'
                . 'LEFT JOIN TrackBundle:Attribute a'
                . '     WITH pa.attrId = a.id'
                . 'WHERE'
                . '     pa.product_id = :id')
                ->setParameter('id', $product->getId());
        $product_attributes = $query->getResult();
        
        return $this->render('product/edit.html.twig', array(
            'product' => $product,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
            'product_attributes' => $product_attributes
        ));
    }
    
    /**
     * Deletes a product entity.
     *
     * @Route("/{id}", name="track_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Product $product)
    {
        $form = $this->createDeleteForm($product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($product);
            $em->flush($product);
        }

        return $this->redirectToRoute('track_index');
    }

    /**
     * Retrieves an SKU and gets an item
     * 
     * @Route("/s/sku/{sku}", name="track_searchSku")
     * @Method("GET")
     */
    public function searchBySku($sku) {
        $em = $this->getDoctrine()->getManager();
        
        $product = $em->getRepository('TrackBundle:Product')
                ->findOneBySku($sku);
        if($product) {
            $id = $product->getId();  
        
            return $this->redirectToRoute("track_show", array('id' => $id));
        } 
        else
        {
            return $this->redirectToRoute("track_index",
                    array('err' => 'nif'));
        }
        
    }
    
    /**
     * Creates a form to delete a product entity.
     *
     * @param Product $product The product entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Product $product)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('track_delete', array('id' => $product->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
    
    /**
     * Add attributes to product
     */
    private function applyAttributeTemplate(Product $product) {
        $em = $this->getDoctrine()->getManager();
        
        // get id to insert into productattr rows
        $type_id = $product->getType();
        
        // find matching attributes to add
        $query = $em->createQuery(''
                . 'SELECT'
                . '     pta.id, '
                . '     IDENTITY(pta.attrId) as attrid, '
                . '     pta.typeId, '
                . '     a.name'
                . ' FROM TrackBundle:ProductTypeAttribute pta'
                . ' LEFT JOIN TrackBundle:Attribute a '
                . '     WITH pta.attrId = a.id'
                . ' WHERE'
                . '     pta.typeId = :type_id')
                ->setParameter('type_id', $type_id);
        $result = $query->getResult();
        
        // apply empty attributes to product
        foreach($result as $attr) {
            $prod_attr = new ProductAttribute();
            $prod_attr->setProductid($product->getId());
            $prod_attr->setAttrId($attr['attrid']);
            
            $em->persist($prod_attr);
        }
        
        $em->flush();
        
    }
    
    
}
