<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet email.')]
#[UniqueEntity(fields: ['username'], message: 'Ce pseudo est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\Length(min: 3, max: 100,
        minMessage: "Votre pseudo doit contenir au moins {{ limit }} characters",
        maxMessage: "Votre pseudo doit contenir au maximum {{ limit }} characters",
    )]
    #[Assert\Regex(
        pattern: "/^[\p{L}\p{M}]+(?:[ '\-][\p{L}\p{M}]+)*$/u",
        message: 'Votre pseudo ne doit pas contenir des characteres speciaux.'
    )]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_WEAK)]
    private ?string $password = null;

    /**
     * @var Collection<int, Post>
     */
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'user_id', orphanRemoval: true)]
    private Collection $posts;

    /**
     * @var Collection<int, Post>
     */
    #[ORM\ManyToMany(targetEntity: Post::class, inversedBy: 'likedBy')]
    private Collection $likedPosts;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'user_id', orphanRemoval: true)]
    private Collection $comments;

    /**
     * @var Collection<int, FriendRequest>
     */
    #[ORM\OneToMany(targetEntity: FriendRequest::class, mappedBy: 'sender', orphanRemoval: true)]
    private Collection $friendRequests;

    /**
     * @var Collection<int, FriendRequest>
     */
    #[ORM\OneToMany(targetEntity: FriendRequest::class, mappedBy: 'receiver', orphanRemoval: true)]
    private Collection $friendRequestsReceived;

    #[ORM\Column(length: 255)]
    #[Assert\Email(message: "Le champ email n'est pas un email valide.")]
    #[Assert\NotBlank(message: "Le champ email ne peut pas être vide.")]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\Image(maxPixels: 160000, maxSize: '1M', extensions: ['jpg','webp','png'],
        maxPixelsMessage: "La taille d'image est trop grande! Taille maximale: 800px x 800px (160000 pixels).",
        extensionsMessage: "Votre format d'image n'est pas accepté! Formats acceptés: jpg, png, webp",
        maxSizeMessage: "!La taille d'image est trop grande. Votre image: ({{ size }} {{ suffix }}). Taille maximale {{ limit }} {{ suffix }}"
    )]
    private ?string $image = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column]
    private ?bool $online = false;

    #[ORM\Column]
    private ?\DateTime $datetime = null;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
        $this->likedPosts = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->friendRequests = new ArrayCollection();
        $this->friendRequestsReceived = new ArrayCollection();
        $this->datetime = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);
        
        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setUserId($this);
        }

        return $this;
    }

    public function removePost(Post $post): static
    {
        if ($this->posts->removeElement($post)) {
            // set the owning side to null (unless already changed)
            if ($post->getUserId() === $this) {
                $post->setUserId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Post>
     */
    public function getLikedPosts(): Collection
    {
        return $this->likedPosts;
    }

    public function addLikedPost(Post $likedPost): static
    {
        if (!$this->likedPosts->contains($likedPost)) {
            $this->likedPosts->add($likedPost);
        }

        return $this;
    }

    public function removeLikedPost(Post $likedPost): static
    {
        $this->likedPosts->removeElement($likedPost);

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setUserId($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getUserId() === $this) {
                $comment->setUserId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FriendRequest>
     */
    public function getFriendRequests(): Collection
    {
        return $this->friendRequests;
    }

    public function addFriendRequest(FriendRequest $friendRequest): static
    {
        if (!$this->friendRequests->contains($friendRequest)) {
            $this->friendRequests->add($friendRequest);
            $friendRequest->setSender($this);
        }

        return $this;
    }

    public function removeFriendRequest(FriendRequest $friendRequest): static
    {
        if ($this->friendRequests->removeElement($friendRequest)) {
            // set the owning side to null (unless already changed)
            if ($friendRequest->getSender() === $this) {
                $friendRequest->setSender(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FriendRequest>
     */
    public function getFriendRequestsReceived(): Collection
    {
        return $this->friendRequestsReceived;
    }

    public function addFriendRequestsReceived(FriendRequest $friendRequestsReceived): static
    {
        if (!$this->friendRequestsReceived->contains($friendRequestsReceived)) {
            $this->friendRequestsReceived->add($friendRequestsReceived);
            $friendRequestsReceived->setReceiver($this);
        }

        return $this;
    }

    public function removeFriendRequestsReceived(FriendRequest $friendRequestsReceived): static
    {
        if ($this->friendRequestsReceived->removeElement($friendRequestsReceived)) {
            // set the owning side to null (unless already changed)
            if ($friendRequestsReceived->getReceiver() === $this) {
                $friendRequestsReceived->setReceiver(null);
            }
        }

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function isOnline(): ?bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): static
    {
        $this->online = $online;

        return $this;
    }
    public function getFriends(): array
    {
        $friends = [];
        foreach ($this->friendRequests as $fr) {
            if ($fr->getStatus() === FriendRequest::STATUS_ACCEPTED) { $friends[] = $fr->getReceiver(); }
        }
        foreach ($this->friendRequestsReceived as $fr) {
            if ($fr->getStatus() === FriendRequest::STATUS_ACCEPTED) { $friends[] = $fr->getSender(); }
        }
        return $friends;
    }
    public function isFriendWith(User $other): bool
    {
        foreach ($this->getFriends() as $friend) {
            if ($friend->getId() === $other->getId()) { return true; }
        }
        return false;
    }

    public function getDatetime(): ?\DateTime
    {
        return $this->datetime;
    }

    public function setDatetime(\DateTime $datetime): static
    {
        $this->datetime = $datetime;

        return $this;
    }
}
