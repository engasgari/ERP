<?php

namespace App\Http\Controllers;

class EmployeeDocumentController extends Controller
{
    public function index()
    {
        return view('employee-documents.index');
    }
}
