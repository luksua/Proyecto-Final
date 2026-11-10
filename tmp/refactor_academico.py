from pathlib import Path
p=Path('taller4-api/app/Http/Controllers')
def update(name,fn):
    f=p/(name+'.php');f.write_text(fn(f.read_text(encoding='utf-8')),encoding='utf-8')
def curso(s):
    s=s.replace('use App\\Models\\Curso;','use App\\Models\\Curso;\nuse App\\Http\\Requests\\CursoRequest;\nuse App\\Services\\CursoService;')
    s=s[:s.index('    // POST /api/v1/cursos')]+'''    public function store(CursoRequest $request, CursoService $service): JsonResponse
    {
        $curso = $service->guardar($request->validated());
        return (new CursoResource($curso->load('docente')))->response()->setStatusCode(201)
            ->header('Location', url("/api/v1/cursos/{$curso->id}"));
    }

    public function update(CursoRequest $request, Curso $curso, CursoService $service)
    {
        return new CursoResource($service->guardar($request->validated(), $curso)->load('docente'));
    }

    public function patch(CursoRequest $request, Curso $curso, CursoService $service)
    {
        return $this->update($request, $curso, $service);
    }

    public function destroy(Curso $curso, CursoService $service)
    {
        $service->eliminar($curso);
        return response()->noContent();
    }
}
'''
    return s
update('CursoController',curso)
def estudiantes(s):
    s=s.replace('use App\\Models\\User;','use App\\Models\\User;\nuse App\\Http\\Requests\\EstudianteRequest;\nuse App\\Services\\EstudianteService;')
    a=s.index('    // POST /api/v1/estudiantes');b=s.index('    // GET /api/v1/estudiantes/{id}',a)
    s=s[:a]+'''    public function store(EstudianteRequest $request, EstudianteService $service): JsonResponse
    {
        $estudiante = $service->crear($request->validated());
        return (new EstudianteResource($estudiante))->response()->setStatusCode(201)
            ->header('Location', url("/api/v1/estudiantes/{$estudiante->id}"));
    }

'''+s[b:]
    a=s.index('    // DELETE /api/v1/estudiantes/{id}')
    return s[:a]+'''    public function destroy(Estudiante $estudiante, EstudianteService $service)
    {
        $service->eliminar($estudiante);
        return response()->noContent();
    }
}
'''
update('EstudianteController',estudiantes)
def docentes(s):
    s=s.replace('use App\\Models\\User;','use App\\Models\\User;\nuse App\\Http\\Requests\\DocenteRequest;\nuse App\\Services\\DocenteService;')
    a=s.index('    // POST /api/v1/docentes')
    return s[:a]+'''    public function store(DocenteRequest $request, DocenteService $service): JsonResponse
    {
        $docente = $service->crear($request->validated());
        return (new DocenteResource($docente))->response()->setStatusCode(201)
            ->header('Location', url("/api/v1/docentes/{$docente->id}"));
    }

    public function destroy(Docente $docente, DocenteService $service)
    {
        $service->eliminar($docente);
        return response()->noContent();
    }
}
'''
update('DocenteController',docentes)
def matriculas(s):
    s=s.replace('use App\\Models\\User;','use App\\Models\\User;')
    s=s.replace('use App\\Models\\Matricula;','use App\\Models\\Matricula;\nuse App\\Http\\Requests\\MatriculaRequest;\nuse App\\Services\\MatriculaService;')
    a=s.index('    // POST /api/v1/matriculas')
    return s[:a]+'''    public function store(MatriculaRequest $request, MatriculaService $service): JsonResponse
    {
        $matricula = $service->crear($request->validated(), $request->user());
        return (new MatriculaResource($matricula->load(['estudiante', 'curso'])))->response()->setStatusCode(201)
            ->header('Location', url("/api/v1/matriculas/{$matricula->id}"));
    }

    public function show(Request $request, Matricula $matricula, MatriculaService $service)
    {
        $service->autorizarLectura($matricula, $request->user());
        return new MatriculaResource($matricula->load(['estudiante', 'curso']));
    }

    public function destroy(Request $request, Matricula $matricula, MatriculaService $service)
    {
        $service->cancelar($matricula, $request->user());
        return response()->noContent();
    }
}
'''
update('MatriculaController',matriculas)
def auth(s):
    s=s.replace('use App\\Models\\User;','use App\\Models\\User;\nuse App\\Http\\Requests\\LoginRequest;')
    s=s.replace('public function login(Request $request)','public function login(LoginRequest $request)')
    a=s.index('        $data = $request->validate(');b=s.index("        $user = User::",a)
    return s[:a]+'        $data = $request->validated();\n\n'+s[b:]
update('AuthController',auth)
