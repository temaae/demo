package multisql

import (
	"fmt"
	"log"
	"sync"

	_ "github.com/lib/pq"

	"github.com/jmoiron/sqlx"
)

type Database struct {
	Host     string
	Dbname   string
	User     string
	Password string
}

type DataStore struct {
	sync.Mutex
	data []map[string]interface{}
}

func (ds *DataStore) append(value map[string]interface{}) {
	ds.data = append(ds.data, value)
}

func (ds *DataStore) Append(value map[string]interface{}) {
	ds.Lock()
	defer ds.Unlock()

	ds.append(value)
}

func NewDataStore() *DataStore {
	return &DataStore{
		data: make([]map[string]interface{}, 0),
	}
}

type Multisql struct {
	DBList []Database
}

func NewMultisql(_dbList []Database) Multisql {
	var msq Multisql
	msq.DBList = _dbList
	return msq
}

func readData(_db Database, storage *DataStore, wg *sync.WaitGroup, sql string, params []interface{}) {

	defer wg.Done()

	connStr := fmt.Sprintf("host=%s user=%s password=%s dbname=%s sslmode=disable", _db.Host, _db.User, _db.Password, _db.Dbname)
	//connStr := "postgres://postgres:123@192.168.56.101/beta1.6?sslmode=disable"
	db, err := sqlx.Connect("postgres", connStr)

	if err != nil {
		log.Fatal(err)
	}
	defer db.Close()

	rows, err := db.Queryx(sql, params...)
	if err != nil {
		log.Fatal(err)
	}
	defer rows.Close()

	for rows.Next() {
		results := make(map[string]interface{})
		err = rows.MapScan(results)
		results["__database__"] = _db.Dbname

		if err != nil {
			log.Fatalln(err)
		}

		storage.Append(results)
	}
}

func (msq *Multisql) SelectData(_bases []string, _sql string, _params []interface{}) []map[string]interface{} {
	storage := NewDataStore()
	var wg sync.WaitGroup

	if len(_bases) == 0 {
		wg.Add(len(msq.DBList))
		for i := range msq.DBList {
			go readData(msq.DBList[i], storage, &wg, _sql, _params)
		}
	} else {
		wg.Add(len(_bases))
		for i := range msq.DBList {
			for j := range _bases {
				if msq.DBList[i].Dbname == _bases[j] {
					go readData(msq.DBList[i], storage, &wg, _sql, _params)
				}
			}
		}
	}

	wg.Wait()

	return storage.data
}
