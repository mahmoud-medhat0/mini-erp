# cash module

Modular-monolith boundary. Layers: application/ (use-cases, Zod, RBAC), domain/ (engines), db/ (repositories — only layer touching Prisma), ui/.
Status: scaffolded in Phase 1; implemented in its roadmap phase. Communicates via Application Services + domain events only.
