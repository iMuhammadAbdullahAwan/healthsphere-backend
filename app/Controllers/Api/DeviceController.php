<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\DeviceReadingModel;
use CodeIgniter\HTTP\ResponseInterface;

class DeviceController extends BaseController
{
    protected DeviceReadingModel $deviceReadingModel;

    public function __construct()
    {
        $this->deviceReadingModel = new DeviceReadingModel();
    }

    /**
     * List device readings with filters and pagination
     * GET /api/devices/readings
     */
    public function index(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $search    = $this->request->getVar('search');
            $status    = $this->request->getVar('status');
            $startDate = $this->request->getVar('start_date');
            $endDate   = $this->request->getVar('end_date');
            $page      = $this->request->getVar('page') ?? 1;
            $limit     = $this->request->getVar('limit') ?? 20;
            $offset    = ($page - 1) * $limit;

            $builder = $this->deviceReadingModel->builder();
            $builder->where('user_id', $this->current_user_id);
            $builder->where('deleted_at', null);

            if ($search) {
                $builder->like('device_name', $search);
            }
            if ($status) {
                $builder->where('status', $status);
            }
            if ($startDate) {
                $builder->where('recorded_at >=', $startDate . ' 00:00:00');
            }
            if ($endDate) {
                $builder->where('recorded_at <=', $endDate . ' 23:59:59');
            }

            $total = $builder->countAllResults(false);
            $readings = $builder->orderBy('recorded_at', 'DESC')->get($limit, $offset)->getResultArray();

            // Format numeric values
            foreach ($readings as &$reading) {
                $reading['reading_value'] = (float)$reading['reading_value'];
            }

            return sendApiResponse([
                'readings' => $readings,
                'pagination' => [
                    'total' => $total,
                    'page'  => (int)$page,
                    'limit' => (int)$limit,
                    'pages' => ceil($total / $limit)
                ]
            ], 'Device readings retrieved successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Get device readings error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve device readings', 500);
        }
    }

    /**
     * Create a new device reading
     * POST /api/devices/readings
     */
    public function create(): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $validationRules = [
                'device_name'   => 'required|string|max_length[255]',
                'reading_value' => 'required|numeric',
                'reading_unit'  => 'permit_empty|string|max_length[50]',
                'recorded_at'   => 'permit_empty|valid_date[Y-m-d H:i:s]',
                'device_image'  => 'permit_empty|uploaded[device_image]|max_size[device_image,10240]|is_image[device_image]',
            ];

            if (!$this->validate($validationRules)) {
                return sendApiResponse($this->validator->getErrors(), 'Validation failed', 400);
            }

            $deviceName   = $this->request->getVar('device_name');
            $readingValue = (float)$this->request->getVar('reading_value');
            $imagePath    = null;

            // Handle File Upload
            $file = $this->request->getFile('device_image');
            if ($file && $file->isValid() && !$file->hasMoved()) {
                $uploadPath = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'devices' . DIRECTORY_SEPARATOR;
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                $fileName = $file->getRandomName();
                $file->move($uploadPath, $fileName);
                $imagePath = 'uploads/devices/' . $fileName;
            }

            // Calculate status if not provided
            $status = $this->request->getVar('status');
            if (!$status) {
                $status = $this->deviceReadingModel->calculateStatus($deviceName, $readingValue);
            }

            $data = [
                'user_id'       => $this->current_user_id,
                'device_name'   => $deviceName,
                'reading_value' => $readingValue,
                'reading_unit'  => $this->request->getVar('reading_unit'),
                'image_path'    => $imagePath,
                'status'        => $status,
                'recorded_at'   => $this->request->getVar('recorded_at') ?? date('Y-m-d H:i:s'),
            ];

            $readingId = $this->deviceReadingModel->insert($data);

            if (!$readingId) {
                return sendApiResponse(null, 'Failed to save device reading', 400);
            }

            $createdReading = $this->deviceReadingModel->builder()->where('id', $readingId)->get()->getRowArray();
            if ($createdReading) {
                $createdReading['reading_value'] = (float)$createdReading['reading_value'];
            }

            return sendApiResponse($createdReading, 'Device reading saved successfully', 201);
        } catch (\Throwable $e) {
            log_message('error', 'Create device reading error: ' . $e->getMessage());
            return sendApiResponse(null, 'An error occurred while saving the reading', 500);
        }
    }

    /**
     * Get single reading detail
     * GET /api/devices/readings/(:num)
     */
    public function show($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $reading = $this->deviceReadingModel->builder()
                ->where('user_id', $this->current_user_id)
                ->where('id', $id)
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();

            if (!$reading) {
                return sendApiResponse(null, 'Device reading not found', 404);
            }

            $reading['reading_value'] = (float)$reading['reading_value'];

            return sendApiResponse($reading, 'Device reading retrieved successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Get device reading error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to retrieve device reading', 500);
        }
    }

    /**
     * Delete a device reading
     * DELETE /api/devices/readings/(:num)
     */
    public function delete($id = null): ResponseInterface
    {
        try {
            if (!$this->current_user_id) {
                return sendApiResponse(null, 'User not authenticated', 401);
            }

            $reading = $this->deviceReadingModel->builder()
                ->where('user_id', $this->current_user_id)
                ->where('id', $id)
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();

            if (!$reading) {
                return sendApiResponse(null, 'Device reading not found', 404);
            }

            $deleted = $this->deviceReadingModel->delete($id);

            if (!$deleted) {
                return sendApiResponse(null, 'Failed to delete device reading', 500);
            }

            // Cleanup image file
            if (!empty($reading['image_path'])) {
                $filePath = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . ltrim($reading['image_path'], '\\/');
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            return sendApiResponse(null, 'Device reading deleted successfully');
        } catch (\Throwable $e) {
            log_message('error', 'Delete device reading error: ' . $e->getMessage());
            return sendApiResponse(null, 'Failed to delete device reading', 500);
        }
    }
}
