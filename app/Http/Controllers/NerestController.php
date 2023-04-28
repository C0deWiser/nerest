<?php

namespace App\Http\Controllers;

use App\Services\NerestService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NerestController extends Controller
{
    public function __construct(public NerestService $storage)
    {
        //
    }

    # GET
    public function view(Request $request, string $path = '')
    {
        $this->storage
            ->withPath($path ?? '')
            ->withToken($request->bearerToken());

        if ($request->all()) {

            if ($request->has('fileExists')) {
                return response()
                    ->json(
                        $this->storage->fileExists()
                    );
            }

            if ($request->has('directoryExists')) {
                return response()
                    ->json(
                        $this->storage->directoryExists()
                    );
            }

            if ($request->has('metadata')) {
                return response()
                    ->json(
                        $this->storage->metadata()
                    );
            }

            if ($request->has('checksum')) {
                return response()
                    ->json(
                        $this->storage->checksum($request->all())
                    );
            }

            if ($request->has('contents')) {
                return response(
                    $this->storage->fileContent()
                );
            }

            if ($request->has('list')) {
                return response()
                    ->json(
                        $this->storage
                            ->listContents($request->boolean('list'))
                            ->toArray()
                    );
            }
        } else {
            return $this->storage
                ->streamResponse();
        }

        throw new BadRequestException;
    }

    # POST
    public function store(Request $request, string $path)
    {
        $this->storage
            ->withPath($path)
            ->withToken($request->bearerToken());

        if ($request->has('contents')) {
            $this->storage
                ->write(
                    $request->string('contents'),
                    $request->except('contents')
                );
        } elseif ($request->has('dir')) {
            $this->storage
                ->createDirectory(
                    $request->all()
                );
        } else {
            throw new BadRequestException;
        }

        return response()->setStatusCode(201);
    }

    # PUT
    public function update(Request $request, string $path)
    {
        $this->storage
            ->withPath($path)
            ->withToken($request->bearerToken());

        if ($request->has('visibility')) {
            $response = $this->storage->visibility($request->string('visibility'));
        } elseif ($request->has('copy')) {
            $response = $this->storage->copy($request->string('copy'));
        } elseif ($request->has('move')) {
            $response = $this->storage->move($request->string('move'));
        } elseif ($request->has('contents')) {
            $response = $this->storage->append($request->string('contents'));
        } else {
            throw new BadRequestException;
        }

        if ($response === false) {
            return response('', 500);
        }

        return response()->noContent();
    }

    # DELETE
    public function destroy(Request $request, string $path)
    {
        $this->storage
            ->withPath($path)
            ->withToken($request->bearerToken());

        if ($this->storage->fileExists()) {
            $response = $this->storage->delete();
        } elseif ($this->storage->directoryExists()) {
            $response = $this->storage->deleteDirectory();
        } else {
            throw new NotFoundHttpException();
        }

        if ($response === false) {
            return response('', 500);
        }

        return response()->noContent();
    }
}
