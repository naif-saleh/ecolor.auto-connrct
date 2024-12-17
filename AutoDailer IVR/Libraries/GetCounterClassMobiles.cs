using Newtonsoft.Json.Linq;
using System.Diagnostics;

public class GetCounterClassMobiles
    {
    public int GetCount(string GetDataResponse)
        {
         var arr = JArray.Parse(GetDataResponse);
        return (int)arr.Count;
        }
    }


