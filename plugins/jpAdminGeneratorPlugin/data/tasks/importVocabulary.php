<?php
/**
 * This task is used to import either a vocabulary or an element set into an instance of the Registry
 *
 * It will look for the XSLT file data/transform/clay2propel.xsl
 *
 * @author  <jphipps@madcreek.com>
 *
 * usage :
 *   symfony import-vocabulary {type} {ID} {file path} -d ('delete existing' -- optional)
 *
 */

pake_desc( 'Import a file into a vocabulary' );
pake_task( 'import-vocabulary' );

function run_import_vocabulary( $task, $args )
{
  DebugBreak();

  //check the argument counts
  if (count($args) < 1)
  {
    throw new Exception('You must provide a vocabulary type.');
  }

  if (count($args) < 2)
  {
    throw new Exception('You must provide a vocabulary id.');
  }

  if (count($args) < 3)
  {
    throw new Exception('You must provide a file name.');
  }

  //set the arguments
  $type          = strtolower($args[0]);
  $id            = $args[1];
  $filePath      = $args[2];
  $deleteMissing = (isset($args[3]) && ("-d" == $args[3]));

  //do some basic validity checks

  if (!in_array($type, array("schema", "vocab", "vocabulary")))
  {
    throw new Exception('You must import into a schema or a vocab');
  }

  if ("vocabulary" == $type)
  {
    $type = "vocab";
  }

  if (!is_numeric($id))
  {
    throw new Exception('You must provide a valid ID');
  }

  //does the file exist?
  if (!file_exists($filePath))
  {
    throw new Exception('You must supply a valid file to import');
  }

  //is the file a valid type?
  if (preg_match( '/^.+\.([[:alpha:]]{2,4})$/', $filePath, $matches))
  {
    if (!in_array(strtolower($matches[1]), array("json", "rdf", "csv", "xml")))
    {
      throw new Exception('You must provide a valid file type based on the extension');
    }
  }
  else
  {
    throw new Exception("File type cannot be determined from the file extension");
  }

  $fileType = $matches[1];

  //we could also prepend these as arguments, but not today
  //define('SF_APP', $app);
  //define('SF_ENVIRONMENT', $env);
  define('SF_APP',         'frontend');
  define('SF_ENVIRONMENT', 'prod');
  define('SF_ROOT_DIR', sfConfig::get('sf_root_dir'));
  define('SF_DEBUG', true);

  require_once(SF_ROOT_DIR.DIRECTORY_SEPARATOR.'apps'.DIRECTORY_SEPARATOR.SF_APP.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.php');

  // initialize database manager
  $databaseManager = new sfDatabaseManager();
  $databaseManager->initialize();

  //is the object a valid object?
  if ('vocab' == $type)
  {
    $vocabObj = VocabularyPeer::retrieveByPK($id);
    if (is_null($vocabObj))
    {
      throw new Exception('Invalid vocabulary ID');
    }

    //set some defaults
    $baseDomain = $vocabObj->getBaseDomain();
    $language = $vocabObj->getLanguage();
    $statusId = $vocabObj->getStatusId();

    //get a skos property id map
    $skosMap = SkosPropertyPeer::getPropertyNames();

  }
  else
  {
    $schemaObj = SchemaPeer::retrieveByPK($id);
    if (is_null($schemaObj))
    {
      throw new Exception('Invalid schema ID');
    }

    //set some defaults
    $baseDomain = $schemaObj->getUri();
    $language = $schemaObj->getLanguage();
    $statusId = $schemaObj->getStatusId();

    //get a element set property id map
    $profileId = 1;
    $profile = ProfilePeer::retrieveByPK($profileId);
    $elementMap = $profile->getAllProperties();

    //there has to be a hash or a slash
    $tSlash = preg_match('@(/$)@i', $baseDomain ) ? '' : '/';
    $tSlash = preg_match('/#$/', $baseDomain ) ? '' : $tSlash;
  }

  //     insert jon's user id
  $userId = 36;

  /* From here on the process is the same regardless of UI */

  //execute
  //     parse file to get the fields/columns and data
  $file = fopen($filePath,"r");
  if (!$file)
  {
    throw new Exception("Can't read supplied file");
  }

  //     check to see if file has been uploaded before
  //          check import history for file name
  $importHistory = FileImportHistoryPeer::retrieveByLastFilePath($filePath);
  //          if reimport
  //               get last import history for filename
  //               unserialize column map
  //               match column names to AP based on map
  //     look for matches in unmatched field/column names to AP (ideal)
  //     csv table of data --
  //          row1: parsed field names/column headers
  //          row2: select dropdown with available fields from object AP (pre-select known matches)
  //                each select identified by column number
  //          row3: display datatype of selected field (updated dynamically when field selected)
  //          row4-13: first 10 rows of parsed data from file
  //     require a column that can match to 'URI' (maybe we'll allow an algorithm later)
  //     require columns that are required by AP
  //     on reimport there should be a flag to 'delete missing properties' from the current data
  //     note: at some point there will be a reimport process that allows URI changing
  //          this will require that there be an OMR identifier embedded in the incoming data

  switch ($fileType)
  {
    case "csv":
      try {
        $reader = new aCsvReader($filePath);
      } catch (Exception $e) {
        throw new Exception("Not a happy CSV file!");
      }

      if ('vocab' == $type)
      {
        // Get array of heading names found
        $headings = $reader->getHeadings();
        $fields = ConceptPeer::getFieldNames();

        //set the map
  //      $map[] = array("property" => "Uri", "column" => "URILocal");
  //      $map[] = array("property" => "prefLabel", "column" => "skos:prefLabel");
  //      $map[] = array("property" => "definition", "column" => "skos:definition");
  //      $map[] = array("property" => "notation", "column" => "skos:notation");
  //      $map[] = array("property" => "scopeNote", "column" => "skos:scopeNote");

        $map = array(
        "uri"        => "URILocal",
        "prefLabel"  => "skos:prefLabel",
        "definition" => "skos:definition",
        "notation"   => "skos:notation",
        "scopeNote"  => "skos:scopeNote");

        //executeImport:

  //    serialize the column map
        try
        {
          while ($row = $reader->getRow()) {
  //        lookup the URI (or the OMR ID if available) for a match
            $uri = $baseDomain . $row[$map["uri"]];
            $concept = ConceptPeer::getConceptByUri($uri);
            $updateTime = time();
            $language = (isset($map['language'])) ? $row[$map['language']] : $vocabObj->getLanguage();

            if (!$concept)
            {
  //          create a new concept or element
              $concept = new Concept();
              $concept->setVocabulary($vocabObj);
              $concept->setUri($uri);
              /**
              * @todo Need to handle updates for topconcept here, just like language
              **/
              $concept->setIsTopConcept(false);
              $concept->updateFromRequest($userId, $row[$map['prefLabel']], $language, $statusId);
            }
            //don't update the concept if the preflabel matches
            else if($row[$map['prefLabel']] != $concept->getPrefLabel())
            {
              $concept->updateFromRequest($userId, $row[$map['prefLabel']]);
            }

            //there needs to be a language to lookup the properties unless it's an objectProperty
            $rowLanguage = (isset($map['language'])) ? $row[$map['language']] : $concept->getLanguage();

            foreach ($map as $key => $value)
            {
              //we skip because we already did them
              if (!in_array($key, array('uri', 'prefLabel', 'language')))
              {
                $skosId = $skosMap[$key];
                //check to see if the property already exists
                $property = ConceptPropertyPeer::lookupProperty($concept->getId(), $skosId, $rowLanguage);

                //create a new property for each unmatched column
                if (!empty($row[$value]))
                {
                  if (!$property)
                  {
                    $property = new ConceptProperty();
                    $property->setCreatedUserId($userId);
                    $property->setConceptId($concept->getId());
                    $property->setCreatedAt($updateTime);
                    $property->setSkosPropertyId($skosId);
                  }

                  if (($row[$value] != $property->getObject()) || ($rowLanguage != $property->getLanguage()))
                  {
                    /**
                    * @todo We need a check here for skos objectproperties and handle differently
                    **/
                    if ($rowLanguage != $property->getLanguage())
                    {
                      $property->setLanguage($rowLanguage);
                    }
                    if ($row[$value] != $property->getObject())
                    {
                      $property->setObject($row[$value]);
                    }
                    $property->setUpdatedUserId($userId);
                    $property->setUpdatedAt($updateTime);
                    $property->save();
                  }
                }
                //the row value is empty
                else if ($deleteMissing && $property)
                {
                  $property->delete();
                }
              }
            }

    //          else
    //               lookup and update concept or element
    //               lookup and update each property
    //          update the history for each property, action is 'import', should be a single timestamp for all (this should be automatic)
    //          if 'delete missing properties' is true
    //               delete each existing, non-required property that wasn't updated by the import
          }
        }
        catch (Exception $e)
        {
    //          catch
    //            if there's an error of any kind, write to error log and continue
        }
      }
      else //it's an element set
      {
        $map = array(
        "uri"         => "uriLocalPart",
        "name"        => "reg:name",
        "definition"  => "skos:definition",
        "label"       => "rdfs:label",
        "note" => array("tag" => "tagCap","ind1" => "ind1Cap","ind2" => "ind2Cap","sub" =>"subCap"));

        //executeImport:
  //    serialize the column map
        try
        {
          while ($row = $reader->getRow()) {
  //        lookup the URI (or the OMR ID if available) for a match

            //There always has to be a URI on either update or create
            if (!isset($row[$map["uri"]]))
            {
              throw new Exception('Missing URI for row: ' . $reader->getRowCount());
              continue;
            }
            $uri = $baseDomain . $tSlash . $row[$map["uri"]];
            $property = SchemaPropertyPeer::retrieveByUri($uri);
            $updateTime = time();
            $rowLanguage = (isset($map['language'])) ? $row[$map['language']] : $language;
            $rowStatusId = (isset($map['status'])) ? $row[$map['status']] : $statusId;

            if (!$property)
            {
  //          create a new property
              /** @var SchemaProperty **/
              $property = new SchemaProperty();
              $property->setSchema($schemaObj);
              $property->setUri($uri);
              $property->setCreatedUserId($userId);
              $property->setCreatedAt($updateTime);
            }

            $property->setLanguage($rowLanguage);
            $property->setStatusId($rowStatusId);
            $property->setUpdatedUserId($userId);
            $property->setUpdatedAt($updateTime);

            if (isset($row[$map["label"]]))
            {
              $property->setLabel($row[$map["label"]]);
            }

            if (isset($row[$map["name"]]))
            {
              $property->setName($row[$map["name"]]);
            }

            if (isset($row[$map["definition"]]))
            {
              $property->setDefinition($row[$map["definition"]]);
            }

              if (is_array($map["note"]))
              {
                $note = '';
                foreach ($map["note"] as $key => $value)
                {
                  $caption = !empty($row[$value]) ? " (" .  $row[$value] . ")" : ' (empty)';
                  $note .= $key . ": " . $row[$key] . $caption . "<br />";
                }
                $property->setNote($note);
              }
              else
              {
                if (isset($row[$map["note"]]))
                {
                  $property->setNote($row[$map["note"]]);
                }
            }
            $property->saveSchemaProperty($userId);

            /**
            * @todo Need to handle domain and range
            **/

            foreach ($map as $key => $value)
            {
              //we skip because we already did them
              if (!in_array($key, array('uri', 'status', 'language', 'label', 'name', 'definition', 'comment', 'note')))
              {
                $elementId = $elementMap[$key];
                //check to see if the property already exists
                //note that this also checks the object value as well, so there's no way to update or delete an existing triple
                //the sheet would have to conatin the identifier for the triple
                $element = SchemaPropertyElementPeer::lookupElement($schemaObj->getId(), $elementId, $map[$value]);

                //create a new property for each unmatched column
                if (!empty($row[$value]))
                {
                  if (!$element)
                  {
                    $element = new SchemaPropertyElement();
                    $element->setCreatedUserId($userId);
                    $element->setCreatedAt($updateTime);
                    $element->setProfilePropertyId($elementId);
                  }

                  if (($row[$value] != $element->getObject()) || ($rowLanguage != $element->getLanguage()))
                  {
                    /**
                    * @todo We need a check here for objectproperties and handle differently
                    **/
                    if ($rowLanguage != $element->getLanguage())
                    {
                      $element->setLanguage($rowLanguage);
                    }
                    if ($row[$value] != $element->getObject())
                    {
                      $element->setObject($row[$value]);
                    }
                    $element->setUpdatedUserId($userId);
                    $element->setUpdatedAt($updateTime);
                    $element->save();
                  }
                }
                //the row value is empty
                else if ($deleteMissing && $element)
                {
                  $element->delete();
                }
              }
            }

    //          else
    //               lookup and update concept or element
    //               lookup and update each property
    //          update the history for each property, action is 'import', should be a single timestamp for all (this should be automatic)
    //          if 'delete missing properties' is true
    //               delete each existing, non-required property that wasn't updated by the import
          }
        }
        catch (Exception $e)
        {
    //          catch
    //            if there's an error of any kind, write to error log and continue
        }
      }
  //     save the import history file (match timestamp to history entries)
      break;
    case "json":
      break;
    case "rdf":
      break;
    case "xml":
      break;
    default:
  }

  /* output to stdout*/
  //          number of objects imported (link to history, filtered on timestamp of import)
  //          number of errors (link to error log)


}
?>