<?php

namespace FOS\CommentBundle\Entity;

use Doctrine\ORM\EntityManager;
use FOS\CommentBundle\Model\CommentManager as BaseCommentManager;
use FOS\CommentBundle\Model\ThreadInterface;
use FOS\CommentBundle\Model\CommentInterface;
use FOS\CommentBundle\Model\Tree;
use InvalidArgumentException;
use DateTime;

class CommentManager extends BaseCommentManager
{
    protected $em;
    protected $repository;
    protected $class;

    /**
     * Constructor.
     *
     * @param EntityManager           $em
     * @param string                  $class
     */
    public function __construct(EntityManager $em, $class)
    {
        $this->em         = $em;
        $this->repository = $em->getRepository($class);
        $this->class      = $em->getClassMetadata($class)->name;
    }

    /*
     * Returns all thread comments in a nested array
     * Will typically be used when it comes to display the comments.
     *
     * @param  string $identifier
     * @return array(
     *     0 => array(
     *         'comment' => CommentInterface,
     *         'children' => array(
     *             0 => array (
     *                 'comment' => CommentInterface,
     *                 'children' => array(...)
     *             ),
     *             1 => array (
     *                 'comment' => CommentInterface,
     *                 'children' => array(...)
     *             )
     *         )
     *     ),
     *     1 => array(
     *         ...
     *     )
     */
    public function findCommentsByThread(ThreadInterface $thread)
    {
        $comments = $this->repository
            ->createQueryBuilder()
            ->where('thread_id = ?', $thread->getIdentifier())
            ->sort('ancestors', 'ASC')
            ->getQuery()
            ->execute();

        $tree = new Tree();
        foreach($comments as $index => $comment) {
            $path = $tree;
            foreach ($comment->getAncestors() as $ancestor) {
                $path = $path->traverse($ancestor);
            }
            $path->add($comment);
        }
        $tree = $tree->toArray();

        return $tree;
    }

    /**
     * Adds a comment
     *
     * @param CommentInterface $comment
     * @param CommentInterface $parent Only used when replying to a specific CommentInterface
     */
    public function addComment(CommentInterface $comment, CommentInterface $parent = null)
    {
        if (null !== $comment->getId()) {
            throw new InvalidArgumentException('Can not add already saved comment');
        }
        if (null === $comment->getThread()) {
            throw new InvalidArgumentException('The comment must have a thread');
        }
        if (null !== $parent) {
            $comment->setAncestors($this->createAncestors($parent));
        }
        $thread = $comment->getThread();
        $thread->setNumComments($thread->getNumComments() + 1);
        $thread->setLastCommentAt(new DateTime());
        $this->em->persist($comment);
        $this->em->flush();
    }

    /**
     * Creates the ancestor array for a given parent
     * Gets the parent ancestors, and adds the parent id.
     *
     * @param CommentInterface $parent
     * @return array
     * @throw InvalidArgumentException if the parent has no ID
     */
    private function createAncestors(CommentInterface $parent)
    {
        if (!$parent->getId()) {
            throw new InvalidArgumentException('The comment parent must have an ID.');
        }
        $ancestors = $parent->getAncestors();
        $ancestors[] = $parent->getId();

        return $ancestors;
    }

    /**
     * Find one comment by its ID
     *
     * @return Comment or null
     **/
    public function findCommentById($id)
    {
        return $this->repository->find($id);
    }

    /**
     * Returns the fully qualified comment thread class name
     *
     * @return string
     **/
    public function getClass()
    {
        return $this->class;
    }
}
