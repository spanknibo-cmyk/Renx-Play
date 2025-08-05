import React, { useState, useEffect } from "react";
import {
  BrowserRouter as Router,
  Routes,
  Route,
  Link,
  useParams,
  useNavigate,
} from "react-router-dom";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Search,
  Plus,
  Edit,
  Trash2,
  Star,
  Calendar,
  Download,
  Settings,
  Heart,
  User,
  ArrowLeft,
  Moon,
  Sun,
  Home,
  ChevronDown,
  Upload,
  Image as ImageIcon,
  X,
} from "lucide-react";
import { apiClient } from "~/client/api";
import { useToast, useAuth, encodeFileAsBase64DataURL } from "~/client/utils";
import {
  Button,
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
  Input,
  Label,
  Textarea,
  Badge,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "~/components/ui";

// Types
type Game = {
  id: string;
  title: string;
  description: string;
  imageUrl: string;
  developer: string;
  version: string;
  engine: string;
  language: string;
  rating: number;
  tags: string;
  downloadUrl?: string | null;
  downloadUrlWindows?: string | null;
  downloadUrlAndroid?: string | null;
  downloadUrlLinux?: string | null;
  downloadUrlMac?: string | null;
  censored: boolean;
  installation: string;
  changelog: string;
  devNotes?: string | null;
  releaseDate: string | Date;
  osWindows: boolean;
  osAndroid: boolean;
  osLinux: boolean;
  osMac: boolean;
  images?: Array<{ id: string; imageUrl: string }>;
  isFavorited?: boolean;
};

// Components
function GameCard({ game }: { game: Game }) {
  const tags = game.tags.split(",").map((tag: string) => tag.trim());

  return (
    <Link to={`/game/${game.id}`} className="block">
      <Card className="game-card overflow-hidden cursor-pointer">
        <div className="game-card-image">
          <img src={game.imageUrl} alt={game.title} loading="lazy" />
        </div>
        <CardHeader className="pb-2">
          <CardTitle className="text-lg line-clamp-2">{game.title}</CardTitle>
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Badge variant="secondary">{game.engine}</Badge>
            <span>{game.version}</span>
          </div>
        </CardHeader>
        <CardContent className="pb-2">
          <p className="text-sm text-muted-foreground line-clamp-3 mb-3">
            {game.description}
          </p>
          <div className="flex items-center gap-2 mb-2">
            <Star className="h-4 w-4 fill-yellow-400 text-yellow-400" />
            <span className="text-sm font-medium">
              {game.rating.toFixed(1)}
            </span>
          </div>
          <div className="flex flex-wrap gap-1">
            {tags.slice(0, 3).map((tag: string, index: number) => (
              <span key={index} className="tag-badge">
                {tag}
              </span>
            ))}
          </div>
        </CardContent>
        <CardFooter className="pt-2">
          <div className="flex items-center justify-between w-full text-xs text-muted-foreground">
            <div className="flex items-center gap-1">
              <Calendar className="h-3 w-3" />
              <span>{new Date(game.releaseDate).toLocaleDateString()}</span>
            </div>
            {(game.downloadUrl ||
              game.downloadUrlWindows ||
              game.downloadUrlAndroid ||
              game.downloadUrlLinux ||
              game.downloadUrlMac) && (
              <Button
                size="sm"
                variant="outline"
                onClick={(e) => {
                  e.stopPropagation();
                  // Prioridade: primeiro link específico disponível, depois genérico
                  const downloadUrl =
                    game.downloadUrlWindows ||
                    game.downloadUrlAndroid ||
                    game.downloadUrlLinux ||
                    game.downloadUrlMac ||
                    game.downloadUrl;
                  if (downloadUrl) {
                    window.open(downloadUrl, "_blank");
                  }
                }}
              >
                <Download className="h-3 w-3 mr-1" />
                Download
              </Button>
            )}
          </div>
        </CardFooter>
      </Card>
    </Link>
  );
}

function Pagination({
  currentPage,
  totalPages,
  onPageChange,
}: {
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
}) {
  if (totalPages <= 1) return null;

  const getVisiblePages = (): (number | string)[] => {
    const delta = 2;
    const range: number[] = [];
    const rangeWithDots: (number | string)[] = [];

    for (
      let i = Math.max(2, currentPage - delta);
      i <= Math.min(totalPages - 1, currentPage + delta);
      i++
    ) {
      range.push(i);
    }

    if (currentPage - delta > 2) {
      rangeWithDots.push(1, "...");
    } else {
      rangeWithDots.push(1);
    }

    rangeWithDots.push(...range);

    if (currentPage + delta < totalPages - 1) {
      rangeWithDots.push("...", totalPages);
    } else {
      rangeWithDots.push(totalPages);
    }

    return rangeWithDots;
  };

  return (
    <div className="flex justify-center items-center gap-2 mt-8">
      <Button
        variant="outline"
        size="sm"
        onClick={() => onPageChange(currentPage - 1)}
        disabled={currentPage === 1}
      >
        Previous
      </Button>

      {getVisiblePages().map((page, index) => (
        <React.Fragment key={index}>
          {page === "..." ? (
            <span className="px-2">...</span>
          ) : (
            <Button
              variant={currentPage === page ? "default" : "outline"}
              size="sm"
              onClick={() => onPageChange(page as number)}
            >
              {page}
            </Button>
          )}
        </React.Fragment>
      ))}

      <Button
        variant="outline"
        size="sm"
        onClick={() => onPageChange(currentPage + 1)}
        disabled={currentPage === totalPages}
      >
        Next
      </Button>
    </div>
  );
}

function GameForm({
  game,
  onSubmit,
  onCancel,
}: {
  game?: Game;
  onSubmit: (data: any) => void;
  onCancel: () => void;
}) {
  const [formData, setFormData] = useState({
    title: game?.title || "",
    description: game?.description || "",
    imageUrl: game?.imageUrl || "",
    developer: game?.developer || "Unknown",
    censored: game?.censored || false,
    version: game?.version || "v1.0",
    engine: game?.engine || "REN'PY",
    language: game?.language || "English",
    osWindows: game?.osWindows !== undefined ? game.osWindows : true,
    osAndroid: game?.osAndroid || false,
    osLinux: game?.osLinux || false,
    osMac: game?.osMac || false,
    installation: game?.installation || "Extract and run",
    changelog: game?.changelog || "Initial release",
    devNotes: game?.devNotes || "",
    rating: game?.rating || 4.5,
    tags: game?.tags || "Adult,Visual Novel",
    downloadUrl: game?.downloadUrl || "",
    downloadUrlWindows: game?.downloadUrlWindows || "",
    downloadUrlAndroid: game?.downloadUrlAndroid || "",
    downloadUrlLinux: game?.downloadUrlLinux || "",
    downloadUrlMac: game?.downloadUrlMac || "",
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSubmit(formData);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <Label htmlFor="title">📌 Título</Label>
          <Input
            id="title"
            value={formData.title}
            onChange={(e) =>
              setFormData({ ...formData, title: e.target.value })
            }
            required
          />
        </div>

        <div>
          <Label htmlFor="developer">🛠️ Desenvolvedor</Label>
          <Input
            id="developer"
            value={formData.developer}
            onChange={(e) =>
              setFormData({ ...formData, developer: e.target.value })
            }
          />
        </div>
      </div>

      <div>
        <Label htmlFor="description">🧠 Descrição</Label>
        <Textarea
          id="description"
          value={formData.description}
          onChange={(e) =>
            setFormData({ ...formData, description: e.target.value })
          }
          required
          rows={4}
        />
      </div>

      <div>
        <Label htmlFor="imageUrl">🖼️ Imagem URL</Label>
        <Input
          id="imageUrl"
          value={formData.imageUrl}
          onChange={(e) =>
            setFormData({ ...formData, imageUrl: e.target.value })
          }
          required
        />
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <Label htmlFor="version">💻 Versão</Label>
          <Input
            id="version"
            value={formData.version}
            onChange={(e) =>
              setFormData({ ...formData, version: e.target.value })
            }
          />
        </div>

        <div>
          <Label htmlFor="engine">🧠 Engine</Label>
          <select
            id="engine"
            value={formData.engine}
            onChange={(e) =>
              setFormData({ ...formData, engine: e.target.value })
            }
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <option value="REN'PY">REN'PY</option>
            <option value="Unity">Unity</option>
            <option value="RPG Maker">RPG Maker</option>
            <option value="HTML">HTML</option>
            <option value="Flash">Flash</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div>
          <Label htmlFor="language">🌐 Língua</Label>
          <Input
            id="language"
            value={formData.language}
            onChange={(e) =>
              setFormData({ ...formData, language: e.target.value })
            }
          />
        </div>
      </div>

      <div>
        <Label>💽 Sistemas Operacionais</Label>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-2">
          <label className="flex items-center space-x-2">
            <input
              type="checkbox"
              checked={formData.osWindows}
              onChange={(e) =>
                setFormData({ ...formData, osWindows: e.target.checked })
              }
              className="rounded border-gray-300"
            />
            <span>Windows</span>
          </label>
          <label className="flex items-center space-x-2">
            <input
              type="checkbox"
              checked={formData.osAndroid}
              onChange={(e) =>
                setFormData({ ...formData, osAndroid: e.target.checked })
              }
              className="rounded border-gray-300"
            />
            <span>Android</span>
          </label>
          <label className="flex items-center space-x-2">
            <input
              type="checkbox"
              checked={formData.osLinux}
              onChange={(e) =>
                setFormData({ ...formData, osLinux: e.target.checked })
              }
              className="rounded border-gray-300"
            />
            <span>Linux</span>
          </label>
          <label className="flex items-center space-x-2">
            <input
              type="checkbox"
              checked={formData.osMac}
              onChange={(e) =>
                setFormData({ ...formData, osMac: e.target.checked })
              }
              className="rounded border-gray-300"
            />
            <span>Mac</span>
          </label>
        </div>
      </div>

      <div>
        <Label htmlFor="rating">🌟 Nota (1-5)</Label>
        <Input
          id="rating"
          type="number"
          min="1"
          max="5"
          step="0.1"
          value={formData.rating.toString()}
          onChange={(e) => {
            const value = e.target.value;
            const numValue = parseFloat(value);
            if (
              value === "" ||
              (!isNaN(numValue) && numValue >= 1 && numValue <= 5)
            ) {
              setFormData({
                ...formData,
                rating: value === "" ? 1 : numValue,
              });
            }
          }}
        />
      </div>

      <div>
        <Label>📥 Links de Download por Plataforma</Label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
          <div>
            <Label htmlFor="downloadUrlWindows">🪟 Windows</Label>
            <Input
              id="downloadUrlWindows"
              value={formData.downloadUrlWindows}
              onChange={(e) =>
                setFormData({ ...formData, downloadUrlWindows: e.target.value })
              }
              placeholder="Link para download Windows"
            />
          </div>
          <div>
            <Label htmlFor="downloadUrlAndroid">🤖 Android</Label>
            <Input
              id="downloadUrlAndroid"
              value={formData.downloadUrlAndroid}
              onChange={(e) =>
                setFormData({ ...formData, downloadUrlAndroid: e.target.value })
              }
              placeholder="Link para download Android"
            />
          </div>
          <div>
            <Label htmlFor="downloadUrlLinux">🐧 Linux</Label>
            <Input
              id="downloadUrlLinux"
              value={formData.downloadUrlLinux}
              onChange={(e) =>
                setFormData({ ...formData, downloadUrlLinux: e.target.value })
              }
              placeholder="Link para download Linux"
            />
          </div>
          <div>
            <Label htmlFor="downloadUrlMac">🍎 Mac</Label>
            <Input
              id="downloadUrlMac"
              value={formData.downloadUrlMac}
              onChange={(e) =>
                setFormData({ ...formData, downloadUrlMac: e.target.value })
              }
              placeholder="Link para download Mac"
            />
          </div>
        </div>
        <div className="mt-2">
          <Label htmlFor="downloadUrl">📥 Link Genérico (opcional)</Label>
          <Input
            id="downloadUrl"
            value={formData.downloadUrl}
            onChange={(e) =>
              setFormData({ ...formData, downloadUrl: e.target.value })
            }
            placeholder="Link genérico de download"
          />
        </div>
      </div>

      <div>
        <Label htmlFor="tags">🏷️ Tags (separadas por vírgula)</Label>
        <Input
          id="tags"
          value={formData.tags}
          onChange={(e) => setFormData({ ...formData, tags: e.target.value })}
        />
      </div>

      <div className="flex gap-2">
        <Button type="submit">{game ? "Atualizar Jogo" : "Criar Jogo"}</Button>
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancelar
        </Button>
      </div>
    </form>
  );
}

// Pages
function HomePage() {
  const [currentPage, setCurrentPage] = useState(1);
  const [searchQuery, setSearchQuery] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");

  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(searchQuery);
      setCurrentPage(1);
    }, 500);

    return () => clearTimeout(timer);
  }, [searchQuery]);

  const { data: gamesData, isLoading } = useQuery(
    ["games", currentPage, debouncedSearch],
    () =>
      apiClient.listGames({
        page: currentPage,
        limit: 12,
        search: debouncedSearch,
      }),
  );

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="mb-8">
        <div className="relative max-w-md mx-auto">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
          <Input
            type="search"
            placeholder="Search games..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-10"
          />
        </div>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
          {Array.from({ length: 12 }).map((_, i) => (
            <Card key={i} className="animate-pulse">
              <div className="aspect-[3/4] bg-muted"></div>
              <CardHeader>
                <div className="h-4 bg-muted rounded w-3/4"></div>
                <div className="h-3 bg-muted rounded w-1/2"></div>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  <div className="h-3 bg-muted rounded"></div>
                  <div className="h-3 bg-muted rounded w-5/6"></div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            {gamesData?.games.map((game) => (
              <GameCard key={game.id} game={game} />
            ))}
          </div>

          {gamesData?.games.length === 0 && (
            <div className="text-center py-12">
              <p className="text-muted-foreground">No games found.</p>
            </div>
          )}

          {gamesData?.pagination && (
            <Pagination
              currentPage={gamesData.pagination.page}
              totalPages={gamesData.pagination.totalPages}
              onPageChange={setCurrentPage}
            />
          )}
        </>
      )}
    </div>
  );
}

function GameDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { toast } = useToast();
  const auth = useAuth();
  const queryClient = useQueryClient();
  const [selectedPlatform, setSelectedPlatform] = useState<string>("");

  const { data: game, isLoading: gameLoading } = useQuery(
    ["game", id],
    () => apiClient.getGame({ id: id! }),
    { enabled: !!id },
  );

  const addToFavoritesMutation = useMutation(apiClient.addToFavorites, {
    onSuccess: () => {
      queryClient.invalidateQueries(["game", id]);
      toast({ title: "Adicionado aos favoritos" });
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao adicionar aos favoritos",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const removeFromFavoritesMutation = useMutation(
    apiClient.removeFromFavorites,
    {
      onSuccess: () => {
        queryClient.invalidateQueries(["game", id]);
        toast({ title: "Removido dos favoritos" });
      },
      onError: (error: any) => {
        toast({
          title: "Erro ao remover dos favoritos",
          description: error.message,
          variant: "destructive",
        });
      },
    },
  );

  if (gameLoading) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="animate-pulse">
          <div className="h-8 bg-muted rounded w-1/4 mb-4"></div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="aspect-[3/4] bg-muted rounded"></div>
            <div className="space-y-4">
              <div className="h-6 bg-muted rounded w-3/4"></div>
              <div className="h-4 bg-muted rounded w-1/2"></div>
              <div className="h-20 bg-muted rounded"></div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (!game) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="text-center">
          <h1 className="text-2xl font-bold mb-4">Jogo não encontrado</h1>
          <Link to="/">
            <Button variant="outline">
              <ArrowLeft className="h-4 w-4 mr-2" />
              Voltar para a lista
            </Button>
          </Link>
        </div>
      </div>
    );
  }

  const tags = game.tags.split(",").map((tag: string) => tag.trim());

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="mb-6">
        <Link to="/">
          <Button variant="outline" size="sm">
            <ArrowLeft className="h-4 w-4 mr-2" />
            Voltar para a lista
          </Button>
        </Link>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <div className="aspect-[3/2] overflow-hidden rounded-lg shadow-lg">
          <img
            src={game.imageUrl}
            alt={game.title}
            className="w-full h-full object-cover hover:scale-105 transition-transform duration-300 cursor-pointer"
            onClick={() => window.open(game.imageUrl, "_blank")}
          />
        </div>

        <div className="space-y-4">
          <div>
            <h1 className="text-3xl font-bold mb-2">{game.title}</h1>
            <div className="flex items-center gap-2 text-sm text-muted-foreground mb-4">
              <Badge variant="secondary">{game.engine}</Badge>
              <span>{game.version}</span>
              <span>•</span>
              <span>{game.developer}</span>
            </div>
          </div>

          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <Star className="h-5 w-5 fill-yellow-400 text-yellow-400" />
              <span className="text-lg font-medium">
                {game.rating.toFixed(1)}
              </span>
            </div>

            {auth.status === "authenticated" && (
              <Button
                variant={game.isFavorited ? "default" : "outline"}
                onClick={() => {
                  if (game.isFavorited) {
                    removeFromFavoritesMutation.mutate({ gameId: id! });
                  } else {
                    addToFavoritesMutation.mutate({ gameId: id! });
                  }
                }}
                disabled={
                  addToFavoritesMutation.isLoading ||
                  removeFromFavoritesMutation.isLoading
                }
              >
                <Heart
                  className={`h-4 w-4 mr-2 ${game.isFavorited ? "fill-current" : ""}`}
                />
                {game.isFavorited
                  ? "Remover dos Favoritos"
                  : "Adicionar aos Favoritos"}
              </Button>
            )}
          </div>

          <p className="text-muted-foreground mb-4">{game.description}</p>

          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <strong>Idioma:</strong> {game.language}
            </div>
            <div>
              <strong>Censurado:</strong> {game.censored ? "Sim" : "Não"}
            </div>
            <div>
              <strong>Lançamento:</strong>{" "}
              {new Date(game.releaseDate).toLocaleDateString()}
            </div>
          </div>

          <div className="mb-4">
            <strong className="text-sm">Plataformas:</strong>
            <div className="flex flex-wrap gap-2 mt-2">
              {game.osWindows && <Badge variant="outline">Windows</Badge>}
              {game.osAndroid && <Badge variant="outline">Android</Badge>}
              {game.osLinux && <Badge variant="outline">Linux</Badge>}
              {game.osMac && <Badge variant="outline">Mac</Badge>}
            </div>
          </div>

          {/* Platform Selection for Download */}
          <div className="mb-4">
            <strong className="text-sm">Selecionar Plataforma:</strong>
            <div className="flex flex-wrap gap-2 mt-2">
              {game.osWindows && game.downloadUrlWindows && (
                <Button
                  variant={
                    selectedPlatform === "windows" ? "default" : "outline"
                  }
                  size="sm"
                  onClick={() => setSelectedPlatform("windows")}
                >
                  🪟 Windows
                </Button>
              )}
              {game.osAndroid && game.downloadUrlAndroid && (
                <Button
                  variant={
                    selectedPlatform === "android" ? "default" : "outline"
                  }
                  size="sm"
                  onClick={() => setSelectedPlatform("android")}
                >
                  🤖 Android
                </Button>
              )}
              {game.osLinux && game.downloadUrlLinux && (
                <Button
                  variant={selectedPlatform === "linux" ? "default" : "outline"}
                  size="sm"
                  onClick={() => setSelectedPlatform("linux")}
                >
                  🐧 Linux
                </Button>
              )}
              {game.osMac && game.downloadUrlMac && (
                <Button
                  variant={selectedPlatform === "mac" ? "default" : "outline"}
                  size="sm"
                  onClick={() => setSelectedPlatform("mac")}
                >
                  🍎 Mac
                </Button>
              )}
            </div>
          </div>

          <div className="mb-4">
            <strong className="text-sm">Tags:</strong>
            <div className="flex flex-wrap gap-1 mt-2">
              {tags.map((tag: string, index: number) => (
                <span key={index} className="tag-badge">
                  {tag}
                </span>
              ))}
            </div>
          </div>

          {(() => {
            let downloadUrl = "";
            let platformName = "";

            if (selectedPlatform === "windows" && game.downloadUrlWindows) {
              downloadUrl = game.downloadUrlWindows;
              platformName = "Windows";
            } else if (
              selectedPlatform === "android" &&
              game.downloadUrlAndroid
            ) {
              downloadUrl = game.downloadUrlAndroid;
              platformName = "Android";
            } else if (selectedPlatform === "linux" && game.downloadUrlLinux) {
              downloadUrl = game.downloadUrlLinux;
              platformName = "Linux";
            } else if (selectedPlatform === "mac" && game.downloadUrlMac) {
              downloadUrl = game.downloadUrlMac;
              platformName = "Mac";
            } else if (game.downloadUrl) {
              downloadUrl = game.downloadUrl;
              platformName = "Jogo";
            }

            return downloadUrl ? (
              <Button asChild className="w-full">
                <a href={downloadUrl} target="_blank" rel="noopener noreferrer">
                  <Download className="h-4 w-4 mr-2" />
                  Baixar {platformName}
                </a>
              </Button>
            ) : (
              <Button disabled className="w-full">
                <Download className="h-4 w-4 mr-2" />
                Selecione uma plataforma
              </Button>
            );
          })()}

          {/* Image Gallery Section - positioned right after download button */}
          {game.images && game.images.length > 0 && (
            <div className="mt-6">
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4">
                {game.images.map((image, index) => (
                  <div key={image.id} className="group relative">
                    <div className="aspect-[4/3] overflow-hidden rounded-lg shadow-md border border-border bg-muted">
                      <img
                        src={image.imageUrl}
                        alt={`Screenshot ${index + 1}`}
                        className="w-full h-full object-cover cursor-pointer hover:scale-105 transition-transform duration-300"
                        onClick={() => window.open(image.imageUrl, "_blank")}
                        loading="lazy"
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {game.devNotes && (
        <Card className="mb-8">
          <CardHeader>
            <CardTitle className="text-lg">Notas do Desenvolvedor</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm whitespace-pre-wrap">{game.devNotes}</p>
          </CardContent>
        </Card>
      )}
    </div>
  );
}

function ImageManager({
  gameId,
  gameName,
}: {
  gameId: string;
  gameName: string;
}) {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);

  const { data: images } = useQuery(["gameImages", gameId], () =>
    apiClient.getGameImages({ gameId }),
  );

  const uploadImageMutation = useMutation(apiClient.uploadGameImage, {
    onSuccess: () => {
      queryClient.invalidateQueries(["gameImages", gameId]);
      setSelectedFile(null);
      setIsUploading(false);
      toast({ title: "Imagem enviada com sucesso" });
    },
    onError: (error: any) => {
      setIsUploading(false);
      toast({
        title: "Erro ao enviar imagem",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const deleteImageMutation = useMutation(apiClient.deleteGameImage, {
    onSuccess: () => {
      queryClient.invalidateQueries(["gameImages", gameId]);
      toast({ title: "Imagem removida com sucesso" });
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao remover imagem",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setSelectedFile(file);
    }
  };

  const handleUpload = async () => {
    if (!selectedFile) return;

    setIsUploading(true);
    try {
      const base64 = await encodeFileAsBase64DataURL(selectedFile);
      if (!base64) {
        throw new Error("Erro ao processar arquivo");
      }

      uploadImageMutation.mutate({
        gameId,
        base64,
        fileName: selectedFile.name,
      });
    } catch {
      setIsUploading(false);
      toast({
        title: "Erro ao processar arquivo",
        variant: "destructive",
      });
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold">
          Galeria de Imagens - {gameName}
        </h3>
      </div>

      <div className="flex items-center gap-2">
        <input
          type="file"
          accept="image/*"
          onChange={handleFileSelect}
          className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        />
        <Button
          onClick={handleUpload}
          disabled={!selectedFile || isUploading}
          size="sm"
        >
          <Upload className="h-4 w-4 mr-2" />
          {isUploading ? "Enviando..." : "Enviar"}
        </Button>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        {images?.map((image) => (
          <div key={image.id} className="relative group">
            <div className="aspect-[4/3] overflow-hidden rounded-lg border bg-muted">
              <img
                src={image.imageUrl}
                alt="Game screenshot"
                className="w-full h-full object-cover"
              />
            </div>
            <Button
              variant="destructive"
              size="sm"
              className="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity"
              onClick={() => {
                if (confirm("Tem certeza que deseja remover esta imagem?")) {
                  deleteImageMutation.mutate({ id: image.id });
                }
              }}
            >
              <X className="h-3 w-3" />
            </Button>
          </div>
        ))}
      </div>

      {images?.length === 0 && (
        <div className="text-center py-8 text-muted-foreground">
          <ImageIcon className="h-12 w-12 mx-auto mb-2 opacity-50" />
          <p>Nenhuma imagem adicionada ainda</p>
        </div>
      )}
    </div>
  );
}

function AdminDashboard() {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [editingGame, setEditingGame] = useState<Game | null>(null);
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [managingImagesFor, setManagingImagesFor] = useState<Game | null>(null);
  const navigate = useNavigate();

  const { data: adminStatus } = useQuery(
    ["adminStatus"],
    apiClient.getAdminStatus,
  );

  const { data: gamesData } = useQuery(["games", 1, ""], () =>
    apiClient.listGames({ page: 1, limit: 50 }),
  );

  const createGameMutation = useMutation(apiClient.createGame, {
    onSuccess: () => {
      queryClient.invalidateQueries(["games"]);
      setShowCreateForm(false);
      toast({ title: "Game created successfully" });
    },
    onError: (error: any) => {
      toast({
        title: "Error creating game",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const updateGameMutation = useMutation(apiClient.updateGame, {
    onSuccess: () => {
      queryClient.invalidateQueries(["games"]);
      setEditingGame(null);
      toast({ title: "Game updated successfully" });
    },
    onError: (error: any) => {
      toast({
        title: "Error updating game",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const deleteGameMutation = useMutation(apiClient.deleteGame, {
    onSuccess: () => {
      queryClient.invalidateQueries(["games"]);
      toast({ title: "Game deleted successfully" });
    },
    onError: (error: any) => {
      toast({
        title: "Error deleting game",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  const setAdminMutation = useMutation(apiClient.setUserAsAdmin, {
    onSuccess: () => {
      queryClient.invalidateQueries(["adminStatus"]);
      toast({ title: "Admin access granted" });
    },
    onError: (error: any) => {
      toast({
        title: "Error setting admin",
        description: error.message,
        variant: "destructive",
      });
    },
  });

  React.useEffect(() => {
    if (adminStatus && !adminStatus.isAdmin) {
      navigate("/");
    }
  }, [adminStatus, navigate]);

  if (!adminStatus?.isAdmin) {
    return (
      <div className="container mx-auto px-4 py-8">
        <Card className="max-w-md mx-auto">
          <CardHeader>
            <CardTitle>Admin Access Required</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="mb-4">You need admin access to view this page.</p>
            <Button onClick={() => setAdminMutation.mutate()}>
              Grant Admin Access
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex justify-between items-center mb-8">
        <h1 className="text-3xl font-bold">Admin Dashboard</h1>
        <Button onClick={() => setShowCreateForm(true)}>
          <Plus className="h-4 w-4 mr-2" />
          Add Game
        </Button>
      </div>

      {showCreateForm && (
        <Card className="mb-8">
          <CardHeader>
            <CardTitle>Create New Game</CardTitle>
          </CardHeader>
          <CardContent>
            <GameForm
              onSubmit={(data) => createGameMutation.mutate(data)}
              onCancel={() => setShowCreateForm(false)}
            />
          </CardContent>
        </Card>
      )}

      {editingGame && (
        <Card className="mb-8">
          <CardHeader>
            <CardTitle>Edit Game</CardTitle>
          </CardHeader>
          <CardContent>
            <GameForm
              game={editingGame}
              onSubmit={(data) =>
                updateGameMutation.mutate({ id: editingGame.id, ...data })
              }
              onCancel={() => setEditingGame(null)}
            />
          </CardContent>
        </Card>
      )}

      {managingImagesFor && (
        <Card className="mb-8">
          <CardHeader>
            <CardTitle>Gerenciar Imagens</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="mb-4">
              <Button
                variant="outline"
                onClick={() => setManagingImagesFor(null)}
              >
                <ArrowLeft className="h-4 w-4 mr-2" />
                Voltar
              </Button>
            </div>
            <ImageManager
              gameId={managingImagesFor.id}
              gameName={managingImagesFor.title}
            />
          </CardContent>
        </Card>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {gamesData?.games.map((game) => (
          <Card key={game.id} className="overflow-hidden">
            <div className="admin-game-card-image">
              <img
                src={game.imageUrl}
                alt={game.title}
                onClick={() => window.open(game.imageUrl, "_blank")}
              />
            </div>
            <CardHeader className="pb-3">
              <CardTitle className="text-lg line-clamp-1">
                {game.title}
              </CardTitle>
            </CardHeader>
            <CardContent className="pb-3">
              <p className="text-sm text-muted-foreground line-clamp-2">
                {game.description}
              </p>
            </CardContent>
            <CardFooter className="flex gap-2 flex-wrap pt-3">
              <Button
                size="sm"
                variant="outline"
                onClick={() => setEditingGame(game)}
              >
                <Edit className="h-3 w-3 mr-1" />
                Edit
              </Button>
              <Button
                size="sm"
                variant="secondary"
                onClick={() => setManagingImagesFor(game)}
              >
                <ImageIcon className="h-3 w-3 mr-1" />
                Imagens
              </Button>
              <Button
                size="sm"
                variant="destructive"
                onClick={() => {
                  if (confirm("Are you sure you want to delete this game?")) {
                    deleteGameMutation.mutate({ id: game.id });
                  }
                }}
              >
                <Trash2 className="h-3 w-3 mr-1" />
                Delete
              </Button>
            </CardFooter>
          </Card>
        ))}
      </div>
    </div>
  );
}

function Navigation() {
  const auth = useAuth();
  const { data: adminStatus } = useQuery(
    ["adminStatus"],
    apiClient.getAdminStatus,
  );
  const [isDark, setIsDark] = useState(false);

  useEffect(() => {
    const savedTheme = localStorage.getItem("theme");
    const systemPrefersDark = window.matchMedia(
      "(prefers-color-scheme: dark)",
    ).matches;

    const shouldUseDark =
      savedTheme === "dark" || (!savedTheme && systemPrefersDark);

    setIsDark(shouldUseDark);

    if (shouldUseDark) {
      document.documentElement.classList.add("dark");
    } else {
      document.documentElement.classList.remove("dark");
    }
  }, []);

  const toggleTheme = () => {
    const newTheme = !isDark;
    setIsDark(newTheme);

    if (newTheme) {
      document.documentElement.classList.add("dark");
      localStorage.setItem("theme", "dark");
    } else {
      document.documentElement.classList.remove("dark");
      localStorage.setItem("theme", "light");
    }
  };

  return (
    <nav className="bg-background border-b sticky top-0 z-50">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16">
          <Link to="/" className="text-xl font-bold">
            Renx-Play
          </Link>
          <div className="flex items-center gap-4">
            <Link to="/">
              <Button variant="ghost">
                <Home className="h-4 w-4 mr-2" />
                Games
              </Button>
            </Link>
            {adminStatus?.isAdmin && (
              <Link to="/admin">
                <Button variant="ghost">
                  <Settings className="h-4 w-4 mr-2" />
                  Admin
                </Button>
              </Link>
            )}

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon">
                  <User className="h-4 w-4" />
                  <ChevronDown className="h-3 w-3 ml-1" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={toggleTheme}>
                  {isDark ? (
                    <Sun className="h-4 w-4 mr-2" />
                  ) : (
                    <Moon className="h-4 w-4 mr-2" />
                  )}
                  {isDark ? "Tema Claro" : "Tema Escuro"}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            {auth.status === "unauthenticated" && (
              <Button onClick={() => auth.signIn()} variant="outline">
                Login
              </Button>
            )}
          </div>
        </div>
      </div>
    </nav>
  );
}

export default function App() {
  return (
    <Router>
      <div className="min-h-screen bg-background text-foreground">
        <Navigation />
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/game/:id" element={<GameDetailPage />} />
          <Route path="/admin" element={<AdminDashboard />} />
        </Routes>
      </div>
    </Router>
  );
}
